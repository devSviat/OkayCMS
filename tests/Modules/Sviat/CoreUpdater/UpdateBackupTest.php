<?php

namespace Modules\Sviat\CoreUpdater;

use Okay\Modules\Sviat\CoreUpdater\Helpers\UpdateBackup;
use PHPUnit\Framework\TestCase;

class UpdateBackupTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/update-backup-test-' . uniqid('', true);
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }

        rmdir($dir);
    }

    // --- вимкнення TLS для mysqldump ---

    public function testSslDisableOptionMatchesClientFlavour(): void
    {
        $this->assertSame(
            '--skip-ssl',
            UpdateBackup::sslDisableOptionFor('mysqldump from 11.8.4-MariaDB, client 10.19')
        );
        $this->assertSame(
            '--ssl-mode=DISABLED',
            UpdateBackup::sslDisableOptionFor('mysqldump  Ver 8.0.43 for Linux on x86_64 (MySQL Community Server - GPL)')
        );
    }

    // --- collectBackupList ---

    public function testCollectBackupListReturnsOnlyFilesThatExistNow(): void
    {
        mkdir($this->tmpDir . '/Okay/Core', 0777, true);
        file_put_contents($this->tmpDir . '/Okay/Core/Existing.php', '<?php');

        $manifestFiles = [
            'Okay/Core/Existing.php' => 'deadbeef',
            'Okay/Core/NewFile.php' => 'cafebabe',
        ];

        $this->assertSame(
            ['Okay/Core/Existing.php'],
            UpdateBackup::collectBackupList($this->tmpDir, $manifestFiles)
        );
    }

    public function testCollectBackupListReturnsEmptyWhenAllFilesAreNew(): void
    {
        $manifestFiles = [
            'Okay/Core/OnlyInPackage.php' => 'deadbeef',
        ];

        $this->assertSame([], UpdateBackup::collectBackupList($this->tmpDir, $manifestFiles));
    }

    public function testCollectBackupListPreservesManifestOrder(): void
    {
        mkdir($this->tmpDir . '/Okay/Core', 0777, true);
        file_put_contents($this->tmpDir . '/Okay/Core/A.php', '<?php');
        file_put_contents($this->tmpDir . '/Okay/Core/B.php', '<?php');

        $manifestFiles = [
            'Okay/Core/B.php' => 'hash-b',
            'Okay/Core/A.php' => 'hash-a',
        ];

        $this->assertSame(
            ['Okay/Core/B.php', 'Okay/Core/A.php'],
            UpdateBackup::collectBackupList($this->tmpDir, $manifestFiles)
        );
    }

    // --- extractTouchedTables ---

    public function testExtractTouchedTablesFindsMarkerInCreateTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS `__products` (\n    `id` INT\n);";

        $this->assertSame(['ok_products'], UpdateBackup::extractTouchedTables([$sql], 'ok_'));
    }

    public function testExtractTouchedTablesFindsMarkerInAlterTable(): void
    {
        $sql = 'ALTER TABLE `__products` ADD COLUMN `foo` INT;';

        $this->assertSame(['ok_products'], UpdateBackup::extractTouchedTables([$sql], 'ok_'));
    }

    public function testExtractTouchedTablesFindsMarkerInInsertInto(): void
    {
        $sql = "INSERT INTO `__settings` (`key`, `value`) VALUES ('a', 'b');";

        $this->assertSame(['ok_settings'], UpdateBackup::extractTouchedTables([$sql], 'ok_'));
    }

    public function testExtractTouchedTablesFindsMarkerInUpdate(): void
    {
        $sql = "UPDATE `__settings` SET `value` = '1' WHERE `key` = 'x';";

        $this->assertSame(['ok_settings'], UpdateBackup::extractTouchedTables([$sql], 'ok_'));
    }

    public function testExtractTouchedTablesFindsMarkerInDropTable(): void
    {
        $sql = 'DROP TABLE IF EXISTS `__old_table`;';

        $this->assertSame(['ok_old_table'], UpdateBackup::extractTouchedTables([$sql], 'ok_'));
    }

    public function testExtractTouchedTablesFindsBothMarkersInRenameTable(): void
    {
        $sql = 'RENAME TABLE `__products` TO `__products_new`;';

        $this->assertSame(
            ['ok_products', 'ok_products_new'],
            UpdateBackup::extractTouchedTables([$sql], 'ok_')
        );
    }

    public function testExtractTouchedTablesFindsMarkerInCreateIndexOnTable(): void
    {
        // Та сама форма, що tests/Core/Release/fixtures/migrations/1.2.0_add_index.up.sql
        // (там таблиця без маркера — це фікстура CoreMigrator::apply(), не нашого коду).
        $sql = 'CREATE INDEX idx_r ON `__test_a` (r);';

        $this->assertSame(['ok_test_a'], UpdateBackup::extractTouchedTables([$sql], 'ok_'));
    }

    public function testExtractTouchedTablesFindsMarkerInDropIndex(): void
    {
        $sql = 'DROP INDEX idx_r ON `__test_a`;';

        $this->assertSame(['ok_test_a'], UpdateBackup::extractTouchedTables([$sql], 'ok_'));
    }

    public function testExtractTouchedTablesFindsMarkerInTruncateTable(): void
    {
        $sql = 'TRUNCATE TABLE `__products`;';

        $this->assertSame(['ok_products'], UpdateBackup::extractTouchedTables([$sql], 'ok_'));
    }

    public function testExtractTouchedTablesFindsMarkerInReplaceInto(): void
    {
        $sql = "REPLACE INTO `__settings` (`key`, `value`) VALUES ('a', 'b');";

        $this->assertSame(['ok_settings'], UpdateBackup::extractTouchedTables([$sql], 'ok_'));
    }

    public function testExtractTouchedTablesFindsMarkerOnContinuationLineOfMultilineAlterTable(): void
    {
        $sql = "ALTER TABLE\n    `__products`\nADD COLUMN `a` INT;";

        $this->assertSame(['ok_products'], UpdateBackup::extractTouchedTables([$sql], 'ok_'));
    }

    public function testExtractTouchedTablesFindsAllMarkersInMultilineRenameTableWithMultiplePairs(): void
    {
        $sql = "RENAME TABLE\n    `__old_a` TO `__new_a`,\n    `__old_b` TO `__new_b`;";

        $this->assertSame(
            ['ok_old_a', 'ok_new_a', 'ok_old_b', 'ok_new_b'],
            UpdateBackup::extractTouchedTables([$sql], 'ok_')
        );
    }

    /**
     * Свідоме рішення: ціль FOREIGN KEY REFERENCES теж вважається "torched",
     * хоча міграція формально пише лише в __order_items. Зайвий рядок у
     * дампі — прийнятна ціна, відсутній — ні.
     */
    public function testExtractTouchedTablesCollectsForeignKeyReferenceTargetInsideCreateTable(): void
    {
        $sql = "CREATE TABLE `__order_items` (\n"
            . "    `order_id` INT,\n"
            . "    FOREIGN KEY (`order_id`) REFERENCES `__orders` (`id`)\n"
            . ');';

        $this->assertSame(
            ['ok_order_items', 'ok_orders'],
            UpdateBackup::extractTouchedTables([$sql], 'ok_')
        );
    }

    public function testExtractTouchedTablesDeduplicatesAcrossStatements(): void
    {
        $statement1 = 'ALTER TABLE `__products` ADD COLUMN `a` INT;';
        $statement2 = "UPDATE `__products` SET `a` = 1;";
        $statement3 = 'ALTER TABLE `__products` ADD COLUMN `b` INT;';

        $this->assertSame(
            ['ok_products'],
            UpdateBackup::extractTouchedTables([$statement1, $statement2, $statement3], 'ok_')
        );
    }

    public function testExtractTouchedTablesIgnoresStatementsWithoutMarkers(): void
    {
        $withoutMarker = 'ALTER TABLE `plain_table` ADD COLUMN `a` INT;';
        $withMarker = 'ALTER TABLE `__products` ADD COLUMN `a` INT;';

        $this->assertSame(
            ['ok_products'],
            UpdateBackup::extractTouchedTables([$withoutMarker, $withMarker], 'ok_')
        );
    }

    public function testExtractTouchedTablesIgnoresQuotedDoubleUnderscoreNotFollowingKeyword(): void
    {
        $sql = "UPDATE `__settings` SET `value` = '__not_a_table';";

        $this->assertSame(['ok_settings'], UpdateBackup::extractTouchedTables([$sql], 'ok_'));
    }

    public function testExtractTouchedTablesReturnsEmptyForNonDdlDmlStatement(): void
    {
        $sql = 'SELECT * FROM `__products`;';

        $this->assertSame([], UpdateBackup::extractTouchedTables([$sql], 'ok_'));
    }

    // --- pruneOldBackups ---

    public function testPruneOldBackupsKeepsThreeNewestByMtime(): void
    {
        $backupsDir = $this->tmpDir . '/backups';
        mkdir($backupsDir);

        $now = time();
        $files = [];
        foreach (['a', 'b', 'c', 'd', 'e'] as $i => $name) {
            $path = $backupsDir . "/{$name}.zip";
            file_put_contents($path, 'x');
            touch($path, $now - (5 - $i) * 60);
            $files[$name] = $path;
        }

        $removed = UpdateBackup::pruneOldBackups($backupsDir, 3);

        $this->assertSame([$files['b'], $files['a']], $removed);
        $this->assertFileDoesNotExist($files['a']);
        $this->assertFileDoesNotExist($files['b']);
        $this->assertFileExists($files['c']);
        $this->assertFileExists($files['d']);
        $this->assertFileExists($files['e']);
    }

    public function testPruneOldBackupsDoesNothingWhenFewerThanKeep(): void
    {
        $backupsDir = $this->tmpDir . '/backups';
        mkdir($backupsDir);
        file_put_contents($backupsDir . '/only.zip', 'x');

        $removed = UpdateBackup::pruneOldBackups($backupsDir, 3);

        $this->assertSame([], $removed);
        $this->assertFileExists($backupsDir . '/only.zip');
    }
}
