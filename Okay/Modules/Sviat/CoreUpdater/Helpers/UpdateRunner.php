<?php

namespace Okay\Modules\Sviat\CoreUpdater\Helpers;

use Okay\Core\Config;
use Okay\Core\Design;
use Okay\Core\Release\CoreMigrationException;
use Okay\Core\Release\CoreMigrator;
use Okay\Core\Request;
use Okay\Core\Settings;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Process\Process;

/**
 * Оркестрація повного циклу оновлення ядра (спек §8, §9): download → verify
 * → preflight → backup → maintenance_on → apply_files → migrations →
 * cache_clear → health_check → finalize, з rollback-гілкою на будь-якому
 * фейлі, що трапився ПІСЛЯ старту apply_files.
 *
 * Кожен крок: UpdateStatus::advance() → дія → UpdateStatus::save().
 * Провал будь-де перехоплюється зовні: UpdateStatus::fail(), тоді за
 * needsRollback() — або rollback-гілка (restoreFiles + повторний
 * health-check зі старою версією), або прямий термінальний 'failed'.
 */
class UpdateRunner
{
    private const LOCK_RESOURCE = 'core_updater_run';

    /**
     * Джерело root URL для health-check, коли Request не може його зібрати
     * (CLI-запуск `ok core:update` не має $_SERVER['HTTP_HOST']) — Plan D
     * додасть адмінську форму, яка пише цей ключ; поки що лише читання.
     */
    public const SETTING_ROOT_URL = 'core_updater__root_url';

    private const MIN_FREE_SPACE_MULTIPLIER = 3;

    /** Ім'я пробного файлу pre-flight; воно ж у `release-manifest.json` серед exclude. */
    private const WRITE_PROBE_NAME = '.core-updater-write-probe';

    /**
     * Health-check після apply б'ється в PHP-FPM, який щойно перечитує
     * підмінені файли — перша спроба ловить і холодний opcache, і не
     * дорозігнаний пул. Ретраї лише розтягують те саме питання в часі,
     * рішення по кожній відповіді лишається строгим.
     */
    private const HEALTH_CHECK_ATTEMPTS = 4;

    private const HEALTH_CHECK_RETRY_DELAY = 2;

    private const SELF_REQUEST_PROBE_TIMEOUT = 10;

    /**
     * Крок → назва методу-обробника, у порядку UpdateStatus::STEPS. ЄДИНЕ
     * джерело правди диспетчерування (dispatchStep() читає саме звідси, а
     * не з окремого switch/match) — інакше тест повноти перевіряв би
     * декоративну константу, а реальний диспетчер міг би непомітно
     * розійтись із нею.
     *
     * @var array<string, string>
     */
    private const STEP_HANDLERS = [
        UpdateStatus::STEP_DOWNLOAD => 'stepDownload',
        UpdateStatus::STEP_VERIFY => 'stepVerify',
        UpdateStatus::STEP_PREFLIGHT => 'stepPreflight',
        UpdateStatus::STEP_BACKUP => 'stepBackup',
        UpdateStatus::STEP_MAINTENANCE_ON => 'stepMaintenanceOn',
        UpdateStatus::STEP_APPLY_FILES => 'stepApplyFiles',
        UpdateStatus::STEP_MIGRATIONS => 'stepMigrations',
        UpdateStatus::STEP_CACHE_CLEAR => 'stepCacheClear',
        UpdateStatus::STEP_HEALTH_CHECK => 'stepHealthCheck',
        UpdateStatus::STEP_FINALIZE => 'stepFinalize',
    ];

    public function __construct(
        private readonly UpdateCheckHelper $checkHelper,
        private readonly UpdateStatus $status,
        private readonly UpdateDownloader $downloader,
        private readonly UpdateBackup $backup,
        private readonly UpdateApplier $applier,
        private readonly CoreMigrator $migrator,
        private readonly Settings $settings,
        private readonly Config $config,
        private readonly Design $design
    ) {
    }

    /** @return array<string, string> крок → метод, для тесту повноти/порядку */
    public static function stepHandlers(): array
    {
        return self::STEP_HANDLERS;
    }

    /**
     * Чи потребує провал на цьому кроці rollback-гілки (спек §9): від
     * apply_files до health_check включно — тобто саме там, де файли вже
     * могли змінитись. finalize навмисно ПОЗА межею: на нього переходять
     * лише після успішного health_check (сайт уже підтверджено живий на
     * новій версії), і провал прибирання тимчасових файлів там — не привід
     * повертати робочий сайт назад до старої версії.
     */
    public static function needsRollback(array $state): bool
    {
        $order = UpdateStatus::STEPS;
        $stepIndex = array_search($state['step'] ?? null, $order, true);
        if ($stepIndex === false) {
            return false;
        }

        $applyIndex = array_search(UpdateStatus::STEP_APPLY_FILES, $order, true);
        $finalizeIndex = array_search(UpdateStatus::STEP_FINALIZE, $order, true);

        return $stepIndex >= $applyIndex && $stepIndex < $finalizeIndex;
    }

    /**
     * @param ?string $targetVersion очікувана версія релізу. Явне значення
     *     працює як запобіжник виклику ("онови до 1.2.0"), а не як вибір
     *     версії: качається завжди останній реліз зі снапшота, і розбіжність
     *     зупиняє прогін. Довільну ціль (у т.ч. відкат на нижчу) вмикає Plan D.
     * @return array<string, mixed> фінальний стан прогону (те саме, що поверне наступний UpdateStatus::load())
     */
    public function run(?string $targetVersion = null): array
    {
        ignore_user_abort(true);
        set_time_limit(0);

        $rootDir = rtrim((string) $this->config->get('root_dir'), '/');

        // Той самий патерн, що Okay/Core/Scheduler/Scheduler.php: FlockStore
        // на files/tmp — реальний flock ОС, тримається до release()/смерті
        // процесу незалежно від TTL (FlockStore::putOffExpiration() — no-op).
        $lockFactory = new LockFactory(new FlockStore($rootDir . '/files/tmp'));
        $lock = $lockFactory->createLock(self::LOCK_RESOURCE);
        if (!$lock->acquire()) {
            throw new \RuntimeException('Оновлення вже виконується.');
        }

        try {
            return $this->runLocked($rootDir, $targetVersion);
        } finally {
            $lock->release();
        }
    }

    private function runLocked(string $rootDir, ?string $targetVersion): array
    {
        $snapshot = $this->checkHelper->getSnapshot() ?? $this->checkHelper->check(true);
        $latest = $snapshot['latest'] ?? null;
        if (!is_array($latest) || !is_string($latest['forkVersion'] ?? null) || !is_array($latest['assets'] ?? null)) {
            throw new \RuntimeException('Немає доступного релізу для оновлення.');
        }

        $toVersion = $latest['forkVersion'];
        $fromVersion = $this->config->forkVersion;

        if ($targetVersion !== null && $targetVersion !== $toVersion) {
            throw new \RuntimeException(
                "Цільова версія {$targetVersion} не збігається з доступним релізом {$toVersion}."
            );
        }

        $resumesDeadRun = $this->resumesDeadRun($toVersion);

        $state = UpdateStatus::fresh($fromVersion, $toVersion);
        $this->status->save($state);

        // Робочі дані кроку (шляхи до тимчасових ресурсів, застосовані
        // файли/міграції) — потрібні наступним крокам того ж прогону й
        // rollback-гілці при фейлі; на відміну від $state, у Settings НЕ
        // зберігаються — run() виконує весь конвеєр в одному процесі,
        // UpdateStatus існує лише для поллінгу прогресу з адмінки.
        $ctx = [
            'rootDir' => $rootDir,
            'toVersion' => $toVersion,
            'fromVersion' => $fromVersion,
            'latest' => $latest,
            'resumesDeadRun' => $resumesDeadRun,
        ];

        $this->logLine($ctx, "run {$fromVersion} → {$toVersion}" . ($resumesDeadRun ? ' (доїзд мертвого прогону)' : ''));

        foreach (UpdateStatus::STEPS as $index => $step) {
            // Перший крок advance() не потребує: fresh() уже поставив його
            // поточним, а advance() на той самий крок ламає "лише вперед".
            if ($index > 0) {
                $state = UpdateStatus::advance($state, $step);
                $this->status->save($state);
            }

            $this->logLine($ctx, "step: {$step}");

            try {
                $ctx = $this->dispatchStep($step, $ctx);
            } catch (\Throwable $e) {
                return $this->handleFailure($state, $ctx, $e);
            }
        }

        $doneExtra = isset($ctx['finalizeWarning']) ? ['finalizeWarning' => $ctx['finalizeWarning']] : [];
        $state = UpdateStatus::advance($state, UpdateStatus::STEP_DONE, $doneExtra);
        $this->status->save($state);

        return $state;
    }

    /**
     * Обірваний посеред apply прогін на ТУ САМУ версію — єдиний випадок, коли
     * downgrade guard треба послабити: Config.php на диску вже новий, тож
     * строгий `>` відмовляє саме в тій дії, що лікує напівзастосоване дерево.
     * Умови всі три разом: збережений стан є, він на цю ж версію, він не
     * термінальний і вже протух (isStale) — тобто процес гарантовано мертвий.
     */
    private function resumesDeadRun(string $toVersion): bool
    {
        $previous = $this->status->load();
        if ($previous === null || ($previous['toVersion'] ?? null) !== $toVersion) {
            return false;
        }

        if (in_array($previous['step'] ?? null, UpdateStatus::TERMINAL_STEPS, true)) {
            return false;
        }

        return UpdateStatus::isStale($previous, time());
    }

    /** @param array<string, mixed> $ctx @return array<string, mixed> */
    private function dispatchStep(string $step, array $ctx): array
    {
        $method = self::STEP_HANDLERS[$step] ?? null;
        if ($method === null) {
            throw new \LogicException("UpdateRunner: невідомий крок '{$step}'");
        }

        /** @var array<string, mixed> $result динамічний виклик — PHPStan не бачить сигнатуру методу з рядка */
        $result = $this->{$method}($ctx);

        return $result;
    }

    // --- кроки ---

    /** @param array<string, mixed> $ctx @return array<string, mixed> */
    private function stepDownload(array $ctx): array
    {
        $localPaths = $this->downloader->download($ctx['latest']['assets'], $ctx['toVersion']);

        $ctx['zipPath'] = $localPaths['zip'];
        $ctx['checksumsPath'] = $localPaths['checksums'];

        return $ctx;
    }

    /** @param array<string, mixed> $ctx @return array<string, mixed> */
    private function stepVerify(array $ctx): array
    {
        $checksumsTxt = file_get_contents($ctx['checksumsPath']);
        if ($checksumsTxt === false) {
            throw new \RuntimeException("Не вдалося прочитати checksums.txt: {$ctx['checksumsPath']}");
        }

        $checksums = UpdatePackage::parseChecksums($checksumsTxt);
        UpdatePackage::verifyArchiveHash($ctx['zipPath'], $checksums);

        $extractDir = $ctx['rootDir'] . '/files/tmp/updates/' . $ctx['toVersion'] . '/extracted';
        $this->downloader->extract($ctx['zipPath'], $extractDir);

        $manifestPath = $extractDir . '/manifest.json';
        $manifestJson = is_file($manifestPath) ? file_get_contents($manifestPath) : false;
        if ($manifestJson === false) {
            throw new \RuntimeException("У пакеті відсутній manifest.json: {$manifestPath}");
        }

        $manifestData = json_decode($manifestJson, true);
        $manifestFiles = is_array($manifestData['files'] ?? null) ? $manifestData['files'] : null;
        if ($manifestFiles === null) {
            throw new \RuntimeException("manifest.json пошкоджений або без ключа 'files': {$manifestPath}");
        }

        UpdatePackage::assertSafePaths($manifestFiles);
        UpdatePackage::verifyExtractedFiles($extractDir . '/payload', $manifestFiles);

        $ctx['extractDir'] = $extractDir;
        $ctx['manifestFiles'] = $manifestFiles;
        $ctx['versionMeta'] = UpdatePackage::readVersionMeta($extractDir);

        return $ctx;
    }

    /** @param array<string, mixed> $ctx @return array<string, mixed> */
    private function stepPreflight(array $ctx): array
    {
        // Тег релізу і version.json усередині пакета — два незалежних джерела
        // версії; розбіжність означає, що до тегу причепили чужий архів.
        $packageVersion = $ctx['versionMeta']['forkVersion'] ?? null;
        if ($packageVersion !== $ctx['toVersion']) {
            throw new \RuntimeException(sprintf(
                'version.json пакета заявляє версію %s, а реліз-тег — %s: пакет не відповідає релізу.',
                is_string($packageVersion) ? $packageVersion : 'н/д',
                $ctx['toVersion']
            ));
        }

        UpdatePackage::assertInstallable(
            $ctx['versionMeta'],
            $ctx['fromVersion'],
            PHP_VERSION,
            (bool) ($ctx['resumesDeadRun'] ?? false)
        );

        $this->assertWritable($ctx['rootDir']);
        $this->assertEnoughDiskSpace($ctx['rootDir'], $ctx['extractDir']);

        $payloadDir = $ctx['extractDir'] . '/payload';
        if ($this->composerLockDiffers($ctx['rootDir'], $payloadDir) && !$this->isComposerAvailable($ctx['rootDir'])) {
            throw new \RuntimeException(
                'composer.lock оновлення відрізняється від поточного, а composer/composer.phar не знайдено — '
                . 'оновлення зупинено до будь-яких змін.'
            );
        }

        if (($ctx['versionMeta']['requiresMigrations'] ?? null) === true && !$this->backup->isMysqldumpAvailable()) {
            throw new \RuntimeException(
                'Цей реліз потребує core-міграцій, а mysqldump недоступний — без нього дамп торкнутих таблиць '
                . 'неможливий, оновлення зупинено до будь-яких змін.'
            );
        }

        $this->assertSelfRequestPossible();

        return $ctx;
    }

    /**
     * Health-check і rollback роблять self-request, поки ЦЕЙ запит тримає
     * воркер. Пул, що не може обслужити другий запит паралельно (наприклад,
     * pm.max_children=1), гарантує провал health-check уже ПІСЛЯ заміни
     * файлів — із сайтом, залишеним у техроботах. Проба зупиняє такий
     * прогін до будь-яких змін; будь-яка HTTP-відповідь (навіть 500)
     * доводить вільний воркер.
     */
    public function assertSelfRequestPossible(): void
    {
        $url = rtrim($this->resolveRootUrl(), '/') . '/';

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => self::SELF_REQUEST_PROBE_TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (!self::isSelfRequestProof($errno, $httpCode)) {
            throw new \RuntimeException(sprintf(
                'Сайт не відповів сам собі за %d с (%s): health-check після заміни файлів упаде так само. '
                . 'Найчастіша причина — PHP-FPM з одним воркером (pm.max_children=1); потрібно щонайменше '
                . '2 вільні воркери. Оновлення зупинено до будь-яких змін.',
                self::SELF_REQUEST_PROBE_TIMEOUT,
                $errno !== 0 ? "cURL #{$errno}: {$error}" : 'відповідь без HTTP-коду'
            ));
        }
    }

    /**
     * Чиста функція рішення проби (без cURL) — покривається юніт-тестом.
     * Код відповіді байдужий: проба доводить наявність вільного воркера,
     * а не здоров'я сторінки.
     */
    public static function isSelfRequestProof(int $errno, int $httpCode): bool
    {
        return $errno === 0 && $httpCode > 0;
    }

    /** @param array<string, mixed> $ctx @return array<string, mixed> */
    private function stepBackup(array $ctx): array
    {
        $rootDir = $ctx['rootDir'];
        $backupsDir = $rootDir . '/files/backups';
        if (!is_dir($backupsDir) && !mkdir($backupsDir, 0777, true) && !is_dir($backupsDir)) {
            throw new \RuntimeException("Не вдалося створити каталог бекапів: {$backupsDir}");
        }

        $this->protectBackupsDir($backupsDir);

        $backupList = UpdateBackup::collectBackupList($rootDir, $ctx['manifestFiles']);
        $backupZipPath = $backupsDir . '/pre-update-' . $ctx['fromVersion'] . '-to-' . $ctx['toVersion'] . '-' . time() . '.zip';

        if ($backupList !== []) {
            $this->backup->createFilesBackup($rootDir, $backupList, $backupZipPath);
        } else {
            // Немає жодного файлу поточного дерева, який апдейт перезапише
            // (напр. дуже маленький реліз) — все одно архів, щоб rollback-
            // гілка мала що відкривати без спецвипадку "нема бекапу". Архів
            // БЕЗ жодного запису libzip на диск не пише взагалі (close()
            // мовчки лишає шлях порожнім) — тому один маркер-запис;
            // UpdateApplier::restoreFiles() зобов'язаний його пропускати.
            $zip = new \ZipArchive();
            $openResult = $zip->open($backupZipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
            if ($openResult !== true) {
                throw new \RuntimeException("Не вдалося створити архів бекапу {$backupZipPath} (код {$openResult}).");
            }
            if (!$zip->addFromString(UpdateBackup::EMPTY_BACKUP_MARKER, '')) {
                $zip->close();
                throw new \RuntimeException("Не вдалося записати маркер порожнього бекапу в {$backupZipPath}.");
            }
            if (!$zip->close()) {
                throw new \RuntimeException("Помилка при закритті архіву бекапу {$backupZipPath}.");
            }
        }
        $ctx['backupZipPath'] = $backupZipPath;
        $this->logLine($ctx, 'backup: ' . $backupZipPath . ' (' . count($backupList) . ' файлів)');

        $migrationsDir = $ctx['extractDir'] . '/migrations';
        $ctx['migrationsDir'] = $migrationsDir;

        $migrationFiles = glob(rtrim($migrationsDir, '/') . '/*.up.sql') ?: [];
        if ($migrationFiles !== []) {
            $statements = [];
            foreach ($migrationFiles as $path) {
                array_push($statements, ...$this->migrator->splitSqlFile($path));
            }

            $tables = UpdateBackup::extractTouchedTables($statements, (string) $this->config->get('db_prefix'));
            if ($tables !== []) {
                $dumpPath = $backupsDir . '/pre-update-' . $ctx['fromVersion'] . '-to-' . $ctx['toVersion'] . '-' . time() . '.sql';
                $this->backup->dumpTables($tables, $dumpPath);
                $ctx['migrationsDumpPath'] = $dumpPath;
                $this->logLine($ctx, 'dump: ' . $dumpPath . ' (' . implode(', ', $tables) . ')');
            }
        }

        return $ctx;
    }

    /** @param array<string, mixed> $ctx @return array<string, mixed> */
    private function stepMaintenanceOn(array $ctx): array
    {
        $flagPath = MaintenanceMode::flagPath($ctx['rootDir']);

        $ctx['maintenanceFlagPath'] = $flagPath;
        $ctx['maintenanceToken'] = MaintenanceMode::enable($flagPath);

        return $ctx;
    }

    /** @param array<string, mixed> $ctx @return array<string, mixed> */
    private function stepApplyFiles(array $ctx): array
    {
        $ctx['appliedPaths'] = $this->applier->applyFiles(
            $ctx['extractDir'] . '/payload',
            $ctx['rootDir'],
            $ctx['manifestFiles']
        );
        $this->logLine($ctx, 'applied ' . count($ctx['appliedPaths']) . ' файлів:');
        foreach ($ctx['appliedPaths'] as $path) {
            $this->logLine($ctx, '  ' . $path);
        }

        $composerOutput = $this->applier->runComposerIfNeeded($ctx['rootDir'], $ctx['extractDir'] . '/payload');
        if ($composerOutput !== null) {
            $ctx['composerOutput'] = $composerOutput;
            $this->logLine($ctx, 'composer: ' . $composerOutput);
        }

        return $ctx;
    }

    /** @param array<string, mixed> $ctx @return array<string, mixed> */
    private function stepMigrations(array $ctx): array
    {
        $ctx['appliedMigrations'] = $this->migrator->apply($ctx['migrationsDir']);
        if ($ctx['appliedMigrations'] !== []) {
            $this->logLine($ctx, 'migrations: ' . implode(', ', $ctx['appliedMigrations']));
        }

        return $ctx;
    }

    /** @param array<string, mixed> $ctx @return array<string, mixed> */
    private function stepCacheClear(array $ctx): array
    {
        $this->applier->clearCaches($this->design);

        return $ctx;
    }

    /** @param array<string, mixed> $ctx @return array<string, mixed> */
    private function stepHealthCheck(array $ctx): array
    {
        if (!$this->checkHealth($ctx['maintenanceToken'], $ctx['toVersion'])) {
            throw new \RuntimeException('Health-check після оновлення не підтвердив нову версію ' . $ctx['toVersion'] . '.');
        }

        return $ctx;
    }

    /** @param array<string, mixed> $ctx @return array<string, mixed> */
    private function stepFinalize(array $ctx): array
    {
        MaintenanceMode::disable($ctx['maintenanceFlagPath']);

        // Останній рядок журналу: далі його каталог видаляється разом із
        // рештою тимчасових файлів, тож писати після цього нікуди.
        $this->logLine($ctx, 'finalize: технічні роботи знято, оновлення завершено');

        // Прибирання тимчасових файлів — best-effort: сайт уже підтверджено
        // живим на новій версії (health_check щойно пройшов), провал тут не
        // має права зіпсувати завершений успішний прогін.
        try {
            $this->removeDirRecursive(dirname($ctx['extractDir']));
        } catch (\Throwable $e) {
            $ctx['finalizeWarning'] = 'Не вдалося прибрати тимчасові файли: ' . $e->getMessage();
        }

        try {
            UpdateBackup::pruneOldBackups($ctx['rootDir'] . '/files/backups');
        } catch (\Throwable $e) {
            $ctx['finalizeWarning'] = trim(($ctx['finalizeWarning'] ?? '') . ' Не вдалося прибрати старі бекапи: ' . $e->getMessage());
        }

        return $ctx;
    }

    // --- health-check ---

    /**
     * Рішення по ОДНІЙ відповіді health-ендпоінта. Виділено окремо й статично
     * саме тому, що це єдине місце, де розходяться forward- і rollback-гілки
     * (див. $acceptVersionlessOk) — решта checkHealth() це cURL, який у тесті
     * все одно підміняється.
     *
     * @param bool $acceptVersionlessOk лише для rollback: відновлена версія
     *     може бути СТАРІШОЮ за появу health-ендпоінта — її index.php не знає
     *     ні прапорця, ні `?core_updater_health`, тож віддає звичайну
     *     сторінку. 200 звідти доводить рівно те, що треба довести: сайт
     *     живий. Forward-гілка лишається строгою — там нова версія
     *     ЗОБОВ'ЯЗАНА відповісти своїм JSON.
     */
    public static function isHealthyResponse(
        int $errno,
        int $httpCode,
        ?string $body,
        string $expectedForkVersion,
        bool $acceptVersionlessOk
    ): bool {
        if ($errno !== 0 || $body === null || $httpCode !== 200) {
            return false;
        }

        $data = json_decode($body, true);
        $forkVersion = is_array($data) ? ($data['forkVersion'] ?? null) : null;

        if (is_string($forkVersion)) {
            return $forkVersion === $expectedForkVersion;
        }

        return $acceptVersionlessOk;
    }

    /**
     * cURL на `{root}/?core_updater_health=1` з токеном обходу maintenance
     * mode У ЗАГОЛОВКУ (не query-параметром — той лишає слід в access-логах);
     * рішення по кожній відповіді — isHealthyResponse().
     *
     * Ретраї всередині, а не навколо виклику: для решти коду це одне питання
     * «сайт живий?», і підміна методу в тестах має накривати його цілком.
     */
    public function checkHealth(string $token, string $expectedForkVersion, bool $acceptVersionlessOk = false): bool
    {
        $url = rtrim($this->resolveRootUrl(), '/') . '/?core_updater_health=1';

        for ($attempt = 1; $attempt <= self::HEALTH_CHECK_ATTEMPTS; $attempt++) {
            if ($attempt > 1) {
                sleep(self::HEALTH_CHECK_RETRY_DELAY);
            }

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['X-Core-Updater-Token: ' . $token],
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);

            $response = curl_exec($ch);
            $errno = curl_errno($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

            $healthy = self::isHealthyResponse(
                $errno,
                $httpCode,
                is_string($response) ? $response : null,
                $expectedForkVersion,
                $acceptVersionlessOk
            );

            if ($healthy) {
                return true;
            }
        }

        return false;
    }

    /**
     * Налаштування має пріоритет над Request: у HTTP-запиті хост приходить
     * від клієнта (Host/X-Forwarded-Host), і на проксі чи multi-domain
     * інсталяції він не зобов'язаний бути адресою, за якою сайт реально
     * відповідає сам собі. Request::getRootUrl(), а не
     * getDomainWithProtocol() — він включає підпапку, тож інсталяція в
     * підкаталозі не питає корінь чужого сайту. Без жодного з двох джерел
     * (CLI без HTTP_HOST і без ключа) — явна помилка, а не запит на "http://".
     */
    private function resolveRootUrl(): string
    {
        $configured = $this->settings->get(self::SETTING_ROOT_URL);
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        if (Request::getDomain() !== '') {
            return Request::getRootUrl();
        }

        throw new \RuntimeException(
            'Не вдалося визначити адресу сайту для health-check: запущено поза HTTP-запитом, а налаштування "'
            . self::SETTING_ROOT_URL . '" порожнє.'
        );
    }

    // --- rollback ---

    /** @param array<string, mixed> $state @param array<string, mixed> $ctx @return array<string, mixed> */
    private function handleFailure(array $state, array $ctx, \Throwable $e): array
    {
        // needsRollback() МУСИТЬ дивитись на крок, на якому стався провал —
        // тобто на $state ДО fail(). UpdateStatus::fail() переписує 'step'
        // на 'failed', якого нема в STEPS; рахувати needsRollback() ПІСЛЯ
        // fail() означає array_search()=false на кожному фейлі — rollback
        // не спрацював би НІКОЛИ.
        $rollbackNeeded = self::needsRollback($state);
        $failedState = UpdateStatus::fail($state, $e->getMessage());

        // Причина провалу лягає в Settings ДО відкату, а не після: rollback
        // сам ходить у мережу й у файли й цілком може не пережити цього
        // процесу. Тоді єдине, що лишиться адмінці — цей запис; без нього
        // вона бачила б застиглий робочий крок і жодного пояснення.
        $this->status->save($failedState);
        $this->logLine($ctx, 'failed на кроці ' . ($state['step'] ?? '?') . ': ' . $e->getMessage());

        if (!$rollbackNeeded) {
            // Мінор: провал ДО apply_files, коли maintenance_on уже встиг
            // виставити прапорець — нічого в файлах/БД не чіпалось, тож
            // прапорець знімається одразу, а не висить до ручного втручання.
            if (isset($ctx['maintenanceFlagPath']) && MaintenanceMode::isActive($ctx['maintenanceFlagPath'])) {
                try {
                    MaintenanceMode::disable($ctx['maintenanceFlagPath']);
                    $failedState['maintenanceDisabledAfterFailure'] = true;
                } catch (\Throwable $disableError) {
                    $failedState['requiresManualIntervention'] = true;
                    $failedState['manualInterventionReason'] = $disableError->getMessage();
                }
            }

            $this->status->save($failedState);

            return $failedState;
        }

        return $this->rollback($failedState, $ctx, $e);
    }

    /**
     * Rollback-гілка (спек §9): відновлення файлів з бекапу, повторний
     * health-check зі СТАРОЮ версією як маркером. Core-міграції НЕ
     * відкочуються (DDL rollback ненадійний) — лишається лише перелік
     * застосованих імен для ручного відновлення з дампу. Maintenance
     * знімається тільки якщо старий код підтвердив себе живим — інакше
     * сайт лишається закритим, а статус явно каже "потрібне ручне
     * втручання" (спек §11).
     *
     * @param array<string, mixed> $failedState
     * @param array<string, mixed> $ctx
     * @return array<string, mixed>
     */
    private function rollback(array $failedState, array $ctx, \Throwable $cause): array
    {
        $appliedMigrations = $cause instanceof CoreMigrationException ? $cause->appliedNames : [];

        $restoreError = null;
        if (isset($ctx['backupZipPath']) && is_file($ctx['backupZipPath'])) {
            try {
                $this->applier->restoreFiles($ctx['backupZipPath'], $ctx['rootDir']);
            } catch (\Throwable $e) {
                // \Throwable, не лише UpdateApplyException: ZipArchive::open()
                // на битому/відсутньому архіві кидає голий RuntimeException —
                // він мусить лишити стан 'rolled_back'+manual-intervention, а
                // не пролетіти повз rollback() неспійманим.
                $restoreError = $e->getMessage();
            }
        } else {
            $restoreError = 'Немає архіву бекапу для відновлення файлів.';
        }

        $healthy = false;
        if (isset($ctx['maintenanceToken'])) {
            try {
                // acceptVersionlessOk: відновлена версія може не мати
                // health-ендпоінта взагалі (перше в житті інсталяції
                // оновлення відкочується на код без нього).
                $healthy = $this->checkHealth($ctx['maintenanceToken'], $ctx['fromVersion'], true);
            } catch (\Throwable $e) {
                $healthy = false;
            }
        }

        $state = UpdateStatus::rolledBack($failedState, $appliedMigrations);
        if ($restoreError !== null) {
            $state['restoreError'] = $restoreError;
        }

        // Шляхи до бекапів — у стані, а не лише в логу: після rollback саме
        // вони потрібні тому, хто добиратиме дані руками.
        $state['backupZipPath'] = $ctx['backupZipPath'] ?? null;
        $state['migrationsDumpPath'] = $ctx['migrationsDumpPath'] ?? null;

        if ($healthy && isset($ctx['maintenanceFlagPath'])) {
            try {
                MaintenanceMode::disable($ctx['maintenanceFlagPath']);
            } catch (\Throwable $disableError) {
                $state['requiresManualIntervention'] = true;
                $state['manualInterventionReason'] = $disableError->getMessage();
            }
        } else {
            $state['requiresManualIntervention'] = true;
            $state['manualInterventionReason'] = 'Health-check після rollback не підтвердив стару версію — '
                . 'технічні роботи лишено увімкненими. Потрібне ручне втручання.';
        }

        $this->status->save($state);
        $this->logLine($ctx, 'rolled_back' . ($restoreError !== null ? ' (restoreError: ' . $restoreError . ')' : ''));

        return $state;
    }

    // --- pre-flight гейти ---

    private function assertWritable(string $rootDir): void
    {
        if (!is_writable($rootDir)) {
            throw new \RuntimeException("Корінь сайту недоступний для запису: {$rootDir}");
        }

        $this->assertDirWritable(
            $rootDir . '/Okay/Core',
            'застосування файлів ядра буде неможливим'
        );

        // config/ окремо від кореня: саме туди пишеться прапорець технічних
        // робіт, і провал там означав би apply над відкритою вітриною.
        $this->assertDirWritable(
            $rootDir . '/config',
            'прапорець технічних робіт не вдасться виставити'
        );
    }

    private function assertDirWritable(string $dir, string $consequence): void
    {
        $probe = $dir . '/' . self::WRITE_PROBE_NAME;

        try {
            if (@file_put_contents($probe, '') === false) {
                throw new \RuntimeException("Каталог {$dir} недоступний для запису — {$consequence}.");
            }
        } finally {
            @unlink($probe);
        }
    }

    /**
     * Бекапи містять повні копії core-файлів, а кореневий .htaccess віддає
     * `files/**.zip` напряму — без цього файлу архів качається будь-ким, хто
     * вгадає ім'я. Каталогу немає в репозиторії (створюється тут), тож
     * репозиторний files/backups/.htaccess прикриває лише свіжий клон;
     * best-effort, бо на nginx правило й так у конфізі.
     */
    private function protectBackupsDir(string $backupsDir): void
    {
        $htaccess = $backupsDir . '/.htaccess';
        if (!is_file($htaccess)) {
            @file_put_contents($htaccess, "order deny,allow \ndeny from all\n");
        }
    }

    /**
     * Журнал прогону — `files/tmp/updates/{version}/apply.log`. Свідомо
     * найтонший можливий: помилка запису логу не має права зупинити чи
     * зіпсувати оновлення, тому все під @ і без винятків назовні.
     *
     * @param array<string, mixed> $ctx
     */
    private function logLine(array $ctx, string $message): void
    {
        $rootDir = $ctx['rootDir'] ?? null;
        $version = $ctx['toVersion'] ?? null;
        if (!is_string($rootDir) || !is_string($version)) {
            return;
        }

        $dir = $rootDir . '/files/tmp/updates/' . $version;
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        @file_put_contents(
            $dir . '/apply.log',
            '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL,
            FILE_APPEND
        );
    }

    private function assertEnoughDiskSpace(string $rootDir, string $extractDir): void
    {
        $extractedSize = $this->dirSize($extractDir);
        $required = $extractedSize * self::MIN_FREE_SPACE_MULTIPLIER;
        $free = disk_free_space($rootDir);

        if ($free === false || $free <= $required) {
            throw new \RuntimeException(sprintf(
                'Недостатньо вільного місця на диску: доступно %s байт, потрібно щонайменше %s.',
                $free === false ? 'н/д' : (string) (int) $free,
                (string) $required
            ));
        }
    }

    private function dirSize(string $dir): int
    {
        $size = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            $size += $file->getSize();
        }

        return $size;
    }

    /**
     * Логіка навмисно дублює UpdateApplier::runComposerIfNeeded() (той
     * метод private і фактично ЗАПУСКАЄ composer install — тут потрібна
     * лише перевірка ДО будь-яких змін, без побічних ефектів).
     */
    private function composerLockDiffers(string $rootDir, string $payloadDir): bool
    {
        $packageLockPath = $payloadDir . '/composer.lock';
        if (!is_file($packageLockPath)) {
            return false;
        }

        $currentLockPath = $rootDir . '/composer.lock';
        $packageLock = file_get_contents($packageLockPath);
        $currentLock = is_file($currentLockPath) ? file_get_contents($currentLockPath) : null;

        return $packageLock !== $currentLock;
    }

    private function isComposerAvailable(string $rootDir): bool
    {
        foreach ([['composer'], ['composer.phar']] as $command) {
            if ($this->commandWorks($command)) {
                return true;
            }
        }

        $rootPhar = $rootDir . '/composer.phar';

        return is_file($rootPhar) && $this->commandWorks(['php', $rootPhar]);
    }

    /** @param list<string> $command */
    private function commandWorks(array $command): bool
    {
        try {
            $process = new Process([...$command, '--version']);
            $process->run();

            return $process->isSuccessful();
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function removeDirRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            /** @var \SplFileInfo $item */
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
