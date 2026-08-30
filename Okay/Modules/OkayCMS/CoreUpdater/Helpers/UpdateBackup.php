<?php

namespace Okay\Modules\OkayCMS\CoreUpdater\Helpers;

use Okay\Core\Config;
use Symfony\Component\Process\Process;

/**
 * Резервне копіювання перед застосуванням оновлення ядра (спек §8.6, §9).
 * Pure-методи рахують, ЩО треба бекапити; thin-методи виконують I/O
 * (ZipArchive, mysqldump) без бізнес-рішень.
 *
 * Політика виклику (реалізує Task 7, крок `preflight`, ДО backup і будь-яких
 * інших змін): якщо version.json пакета заявляє `requiresMigrations` і
 * isMysqldumpAvailable() === false — стоп RuntimeException-ом. Спек §8.6
 * вимагає дамп торкнутих таблиць перед міграціями; без mysqldump відкат
 * зіпсованих даних неможливий, тож застосування такого пакета не
 * розпочинається взагалі (fail-safe).
 */
class UpdateBackup
{
    /**
     * Той самий патерн, що CoreMigrator::prefixTables()
     * (Okay/Core/Release/CoreMigrator.php) — тримати синхронізованим при
     * зміні того методу. Група 2 включає один "межовий" символ одразу
     * після назви таблиці (не лапка) — його відкидає extractTouchedTables().
     */
    private const MARKER_PATTERN = '/([^"\'0-9a-z_])__([a-z_]+[^"\'])/i';

    /**
     * Стейтменти, що реально можуть зіпсувати дані таблиці. SELECT та інші
     * читальні запити міграціям не властиві й тут не ціль.
     */
    private const STATEMENT_TYPE_PATTERN = '/^\s*(?:CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?'
        . '|ALTER\s+TABLE|INSERT\s+(?:IGNORE\s+)?INTO|UPDATE|DROP\s+TABLE(?:\s+IF\s+EXISTS)?'
        . '|RENAME\s+TABLE)\b/i';

    public function __construct(private readonly Config $config)
    {
    }

    /**
     * Файли з manifest.json, які фізично існують у корені зараз — саме їх
     * перезапише apply, і саме їх (а не увесь manifest) треба бекапити.
     *
     * @param array<string, mixed> $manifestFiles відносний шлях => sha256
     * @return list<string> відносні шляхи наявних файлів, у порядку manifest
     */
    public static function collectBackupList(string $rootDir, array $manifestFiles): array
    {
        $rootDir = rtrim($rootDir, '/');
        $existing = [];

        foreach (array_keys($manifestFiles) as $relativePath) {
            if (is_file($rootDir . '/' . $relativePath)) {
                $existing[] = $relativePath;
            }
        }

        return $existing;
    }

    /**
     * Імена таблиць, яких торкаються SQL-міграції — щоб дамп бекапу не тяг
     * усю базу, а лише те, що апдейт реально змінює. Сканує по рядках:
     * рядок без DDL/DML-ключового слова або без `__`-маркера просто
     * ігнорується.
     *
     * @param string[] $migrationSqlContents вміст .up.sql файлів
     * @return list<string> унікальні імена таблиць, уже з префіксом
     */
    public static function extractTouchedTables(array $migrationSqlContents, string $prefix): array
    {
        $tables = [];

        foreach ($migrationSqlContents as $sql) {
            foreach (preg_split('/\r\n|\r|\n/', $sql) as $line) {
                if (!preg_match(self::STATEMENT_TYPE_PATTERN, $line)) {
                    continue;
                }

                if (preg_match_all(self::MARKER_PATTERN, $line, $matches)) {
                    foreach ($matches[2] as $marker) {
                        // Останній символ групи 2 — межовий, не частина назви.
                        $tables[$prefix . substr($marker, 0, -1)] = true;
                    }
                }
            }
        }

        return array_keys($tables);
    }

    /**
     * Лишає $keep найновіших бекапів (за mtime, за іменем при однаковому
     * mtime), решту видаляє. Спек §9.
     *
     * @return list<string> видалені шляхи
     */
    public static function pruneOldBackups(string $backupsDir, int $keep = 3): array
    {
        $files = array_filter(glob(rtrim($backupsDir, '/') . '/*') ?: [], 'is_file');

        usort($files, static function (string $a, string $b): int {
            $byMtime = filemtime($b) <=> filemtime($a);
            return $byMtime !== 0 ? $byMtime : strcmp($b, $a);
        });

        $removed = [];
        foreach (array_slice($files, $keep) as $stale) {
            if (unlink($stale)) {
                $removed[] = $stale;
            }
        }

        return $removed;
    }

    /**
     * @param string[] $relativePaths з collectBackupList() — лише файли, що
     *     реально існують у $rootDir
     */
    public function createFilesBackup(string $rootDir, array $relativePaths, string $backupZipPath): void
    {
        $rootDir = rtrim($rootDir, '/');

        $zip = new \ZipArchive();
        $openResult = $zip->open($backupZipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        if ($openResult !== true) {
            throw new \RuntimeException("Не вдалося створити архів бекапу {$backupZipPath} (код {$openResult}).");
        }

        foreach ($relativePaths as $relativePath) {
            if (!$zip->addFile($rootDir . '/' . $relativePath, $relativePath)) {
                $zip->close();
                throw new \RuntimeException("Не вдалося додати файл у бекап: {$relativePath}");
            }
        }

        if (!$zip->close()) {
            throw new \RuntimeException("Помилка при закритті архіву бекапу {$backupZipPath}.");
        }
    }

    public function isMysqldumpAvailable(): bool
    {
        try {
            $process = new Process(['mysqldump', '--version']);
            $process->run();

            return $process->isSuccessful();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param string[] $tables вже префіксовані назви таблиць
     *     (extractTouchedTables())
     */
    public function dumpTables(array $tables, string $outFile): void
    {
        if ($tables === []) {
            throw new \RuntimeException('dumpTables(): порожній перелік таблиць — дампити нічого.');
        }

        $handle = fopen($outFile, 'wb');
        if ($handle === false) {
            throw new \RuntimeException("Не вдалося відкрити файл для дампу: {$outFile}");
        }

        $command = array_merge(
            [
                'mysqldump',
                '--single-transaction',
                '--no-tablespaces',
                '-h', (string) $this->config->get('db_server'),
                '-u', (string) $this->config->get('db_user'),
                (string) $this->config->get('db_name'),
            ],
            $tables
        );

        // Пароль через env, не argv: argv видно будь-кому через `ps aux`.
        $process = new Process($command, null, ['MYSQL_PWD' => (string) $this->config->get('db_password')]);
        $process->setTimeout(600);

        try {
            $process->run(static function (string $type, string $buffer) use ($handle): void {
                if ($type === Process::OUT) {
                    fwrite($handle, $buffer);
                }
            });
        } finally {
            fclose($handle);
        }

        if (!$process->isSuccessful()) {
            @unlink($outFile);
            throw new \RuntimeException('mysqldump завершився з помилкою: ' . trim($process->getErrorOutput()));
        }
    }
}
