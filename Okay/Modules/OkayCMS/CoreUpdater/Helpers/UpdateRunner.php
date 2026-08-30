<?php

namespace Okay\Modules\OkayCMS\CoreUpdater\Helpers;

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

    /**
     * Крок → назва методу-обробника, у порядку UpdateStatus::STEPS.
     * Фактичне диспетчерування в run() іде через типізований match()
     * (щоб PHPStan бачив кожен виклик) — ця мапа лише контракт, який
     * тестує повноту й порядок: кожен крок зі спеку §8 має обробник.
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
     * @return array<string, mixed> фінальний стан прогону (те саме, що поверне наступний UpdateStatus::load())
     */
    public function run(): array
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
            return $this->runLocked($rootDir);
        } finally {
            $lock->release();
        }
    }

    private function runLocked(string $rootDir): array
    {
        $snapshot = $this->checkHelper->getSnapshot() ?? $this->checkHelper->check(true);
        $latest = $snapshot['latest'] ?? null;
        if (!is_array($latest) || !is_string($latest['forkVersion'] ?? null) || !is_array($latest['assets'] ?? null)) {
            throw new \RuntimeException('Немає доступного релізу для оновлення.');
        }

        $toVersion = $latest['forkVersion'];
        $fromVersion = $this->config->forkVersion;

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
        ];

        foreach (UpdateStatus::STEPS as $index => $step) {
            // Перший крок advance() не потребує: fresh() уже поставив його
            // поточним, а advance() на той самий крок ламає "лише вперед".
            if ($index > 0) {
                $state = UpdateStatus::advance($state, $step);
                $this->status->save($state);
            }

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

    /** @param array<string, mixed> $ctx @return array<string, mixed> */
    private function dispatchStep(string $step, array $ctx): array
    {
        return match ($step) {
            UpdateStatus::STEP_DOWNLOAD => $this->stepDownload($ctx),
            UpdateStatus::STEP_VERIFY => $this->stepVerify($ctx),
            UpdateStatus::STEP_PREFLIGHT => $this->stepPreflight($ctx),
            UpdateStatus::STEP_BACKUP => $this->stepBackup($ctx),
            UpdateStatus::STEP_MAINTENANCE_ON => $this->stepMaintenanceOn($ctx),
            UpdateStatus::STEP_APPLY_FILES => $this->stepApplyFiles($ctx),
            UpdateStatus::STEP_MIGRATIONS => $this->stepMigrations($ctx),
            UpdateStatus::STEP_CACHE_CLEAR => $this->stepCacheClear($ctx),
            UpdateStatus::STEP_HEALTH_CHECK => $this->stepHealthCheck($ctx),
            UpdateStatus::STEP_FINALIZE => $this->stepFinalize($ctx),
            // UpdateStatus::STEPS типізовано як list<string> — PHPStan не
            // звужує це до літерального union, тож дефолт потрібен лише
            // йому; на практиці сюди приходять виключно значення STEPS.
            default => throw new \LogicException("UpdateRunner: невідомий крок '{$step}'"),
        };
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
        UpdatePackage::assertInstallable($ctx['versionMeta'], $ctx['fromVersion'], PHP_VERSION);

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

        return $ctx;
    }

    /** @param array<string, mixed> $ctx @return array<string, mixed> */
    private function stepBackup(array $ctx): array
    {
        $rootDir = $ctx['rootDir'];
        $backupsDir = $rootDir . '/files/backups';
        if (!is_dir($backupsDir) && !mkdir($backupsDir, 0777, true) && !is_dir($backupsDir)) {
            throw new \RuntimeException("Не вдалося створити каталог бекапів: {$backupsDir}");
        }

        $backupList = UpdateBackup::collectBackupList($rootDir, $ctx['manifestFiles']);
        $backupZipPath = $backupsDir . '/pre-update-' . $ctx['fromVersion'] . '-to-' . $ctx['toVersion'] . '-' . time() . '.zip';

        if ($backupList !== []) {
            $this->backup->createFilesBackup($rootDir, $backupList, $backupZipPath);
        } else {
            // Немає жодного файлу поточного дерева, який апдейт перезапише
            // (напр. дуже маленький реліз) — все одно порожній архів, щоб
            // rollback-гілка мала що відкривати без спецвипадку "нема бекапу".
            $zip = new \ZipArchive();
            $zip->open($backupZipPath, \ZipArchive::CREATE);
            $zip->close();
        }
        $ctx['backupZipPath'] = $backupZipPath;

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

        $composerOutput = $this->applier->runComposerIfNeeded($ctx['rootDir'], $ctx['extractDir'] . '/payload');
        if ($composerOutput !== null) {
            $ctx['composerOutput'] = $composerOutput;
        }

        return $ctx;
    }

    /** @param array<string, mixed> $ctx @return array<string, mixed> */
    private function stepMigrations(array $ctx): array
    {
        $ctx['appliedMigrations'] = $this->migrator->apply($ctx['migrationsDir']);

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
     * cURL на `{root}/?core_updater_health=1` з токеном обходу maintenance
     * mode У ЗАГОЛОВКУ (не query-параметром — той лишає слід в access-логах).
     * Порівняння forkVersion зі СТРОГОЮ рівністю, не "не порожньо": так
     * відсіюється кешована проксі-сторінка старої версії.
     */
    public function checkHealth(string $token, string $expectedForkVersion, ?string $rootUrl = null): bool
    {
        $rootUrl = $rootUrl ?? $this->resolveRootUrl();
        $url = rtrim($rootUrl, '/') . '/?core_updater_health=1';

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
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0 || !is_string($response) || $httpCode !== 200) {
            return false;
        }

        $data = json_decode($response, true);
        $forkVersion = is_array($data) ? ($data['forkVersion'] ?? null) : null;

        return is_string($forkVersion) && $forkVersion === $expectedForkVersion;
    }

    /**
     * Request::getDomainWithProtocol() читає $_SERVER['HTTP_HOST'], якого
     * немає в CLI (`ok core:update`) — Request::getDomain() тоді повертає
     * ''. Фолбек — налаштування self::SETTING_ROOT_URL (Plan D додасть
     * адмінську форму для нього); без жодного з двох — явна помилка, а не
     * тихий запит на "http://".
     */
    private function resolveRootUrl(): string
    {
        if (Request::getDomain() !== '') {
            return Request::getDomainWithProtocol();
        }

        $configured = $this->settings->get(self::SETTING_ROOT_URL);
        if (is_string($configured) && $configured !== '') {
            return $configured;
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
        $failedState = UpdateStatus::fail($state, $e->getMessage());

        if (!self::needsRollback($failedState)) {
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
            } catch (UpdateApplyException $e) {
                $restoreError = $e->getMessage();
            }
        } else {
            $restoreError = 'Немає архіву бекапу для відновлення файлів.';
        }

        $healthy = false;
        if (isset($ctx['maintenanceToken'])) {
            try {
                $healthy = $this->checkHealth($ctx['maintenanceToken'], $ctx['fromVersion']);
            } catch (\Throwable $e) {
                $healthy = false;
            }
        }

        $state = UpdateStatus::rolledBack($failedState, $appliedMigrations);
        if ($restoreError !== null) {
            $state['restoreError'] = $restoreError;
        }

        if ($healthy && isset($ctx['maintenanceFlagPath'])) {
            MaintenanceMode::disable($ctx['maintenanceFlagPath']);
        } else {
            $state['requiresManualIntervention'] = true;
            $state['manualInterventionReason'] = 'Health-check після rollback не підтвердив стару версію — '
                . 'технічні роботи лишено увімкненими. Потрібне ручне втручання.';
        }

        $this->status->save($state);

        return $state;
    }

    // --- pre-flight гейти ---

    private function assertWritable(string $rootDir): void
    {
        if (!is_writable($rootDir)) {
            throw new \RuntimeException("Корінь сайту недоступний для запису: {$rootDir}");
        }

        $probe = $rootDir . '/Okay/Core/.core-updater-write-probe';
        if (@file_put_contents($probe, '') === false) {
            throw new \RuntimeException('Не вдалося записати пробний файл у Okay/Core — застосування файлів ядра буде неможливим.');
        }
        @unlink($probe);
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
