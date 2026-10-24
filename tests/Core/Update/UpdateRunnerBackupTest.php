<?php

namespace Core\Update;

use Aura\Sql\ExtendedPdo;
use Okay\Core\Config;
use Okay\Core\Design;
use Okay\Core\Release\CoreMigrator;
use Okay\Core\Settings;
use Okay\Core\Update\UpdateApplier;
use Okay\Core\Update\UpdateBackup;
use Okay\Core\Update\UpdateCheckHelper;
use Okay\Core\Update\UpdateDownloader;
use Okay\Core\Update\UpdateRunner;
use Okay\Core\Update\UpdateStatus;
use PHPUnit\Framework\TestCase;

/**
 * Дамп перед міграціями: лише таблиці, що вже існують. Міграція 1.4.0
 * створює `__manager_totp`, і mysqldump на ній падав — оновлення зупинялось
 * на кроці backup у кожного сайту (знайдено живим прогоном 1.3.12 → 1.4.0).
 */
class UpdateRunnerBackupTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/runner-backup-' . uniqid();
        mkdir($this->root . '/extracted/migrations', 0777, true);
        file_put_contents($this->root . '/extracted/migrations/1.4.0_new.up.sql',
            "CREATE TABLE IF NOT EXISTS `__manager_totp` (`manager_id` int NOT NULL);\n"
            . "ALTER TABLE `__managers` ADD COLUMN IF NOT EXISTS `x` int NOT NULL DEFAULT 0;\n");
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function testExistingTablesKeepsOnlyTablesThatExist(): void
    {
        $pdo = $this->createStub(ExtendedPdo::class);
        $pdo->method('fetchCol')->willReturn(['ok_managers', 'ok_products']);

        $migrator = new CoreMigrator($pdo);

        $this->assertSame(['ok_managers'], $migrator->existingTables(['ok_manager_totp', 'ok_managers']));
        $this->assertSame([], $migrator->existingTables(['ok_manager_totp']));
    }

    /** @param string[] $existing */
    private function backupStep(array $existing, UpdateBackup $backup): array
    {
        $config = $this->createStub(Config::class);
        $config->method('get')->willReturnMap([['db_prefix', 'ok_']]);

        $pdo = $this->createStub(ExtendedPdo::class);
        $pdo->method('fetchCol')->willReturn($existing);

        $runner = new UpdateRunner(
            $this->createStub(UpdateCheckHelper::class),
            $this->createStub(UpdateStatus::class),
            $this->createStub(UpdateDownloader::class),
            $backup,
            $this->createStub(UpdateApplier::class),
            new CoreMigrator($pdo),
            $this->createStub(Settings::class),
            $config,
            $this->createStub(Design::class)
        );

        return (new \ReflectionMethod(UpdateRunner::class, 'stepBackup'))->invoke($runner, [
            'rootDir' => $this->root,
            'fromVersion' => '1.3.12',
            'toVersion' => '1.4.0',
            'manifestFiles' => [],
            'extractDir' => $this->root . '/extracted',
        ]);
    }

    public function testTableTheMigrationCreatesIsNotDumped(): void
    {
        $backup = $this->createMock(UpdateBackup::class);
        $backup->expects($this->once())->method('dumpTables')->with(['ok_managers'], $this->anything());

        $ctx = $this->backupStep(['ok_managers'], $backup);

        $this->assertArrayHasKey('migrationsDumpPath', $ctx);
    }

    public function testNothingIsDumpedWhenEveryTouchedTableIsNew(): void
    {
        $backup = $this->createMock(UpdateBackup::class);
        $backup->expects($this->never())->method('dumpTables');

        $ctx = $this->backupStep([], $backup);

        $this->assertArrayNotHasKey('migrationsDumpPath', $ctx);
    }
}
