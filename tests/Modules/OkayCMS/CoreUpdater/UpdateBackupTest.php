<?php

namespace Modules\OkayCMS\CoreUpdater;

use Okay\Modules\OkayCMS\CoreUpdater\Helpers\UpdateBackup;
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

    public function testExtractTouchedTablesDeduplicatesAcrossFilesAndStatements(): void
    {
        $migration1 = 'ALTER TABLE `__products` ADD COLUMN `a` INT;';
        $migration2 = "UPDATE `__products` SET `a` = 1;\nALTER TABLE `__products` ADD COLUMN `b` INT;";

        $this->assertSame(
            ['ok_products'],
            UpdateBackup::extractTouchedTables([$migration1, $migration2], 'ok_')
        );
    }

    public function testExtractTouchedTablesIgnoresLinesWithoutMarkers(): void
    {
        $sql = "-- коментар без маркера\nSELECT * FROM `ok_products`;\nALTER TABLE `__products` ADD COLUMN `a` INT;\n\n";

        $this->assertSame(['ok_products'], UpdateBackup::extractTouchedTables([$sql], 'ok_'));
    }

    public function testExtractTouchedTablesIgnoresQuotedDoubleUnderscoreNotFollowingKeyword(): void
    {
        $sql = "UPDATE `__settings` SET `value` = '__not_a_table';";

        $this->assertSame(['ok_settings'], UpdateBackup::extractTouchedTables([$sql], 'ok_'));
    }

    public function testExtractTouchedTablesReturnsEmptyForNonDdlDmlContent(): void
    {
        $sql = "SELECT * FROM `__products`;\n-- __commented_table";

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
