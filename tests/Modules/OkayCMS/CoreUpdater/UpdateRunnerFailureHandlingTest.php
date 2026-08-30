<?php

namespace Modules\OkayCMS\CoreUpdater;

use Okay\Core\Config;
use Okay\Core\Design;
use Okay\Core\Release\CoreMigrationException;
use Okay\Core\Release\CoreMigrator;
use Okay\Core\Settings;
use Okay\Modules\OkayCMS\CoreUpdater\Helpers\MaintenanceMode;
use Okay\Modules\OkayCMS\CoreUpdater\Helpers\UpdateApplier;
use Okay\Modules\OkayCMS\CoreUpdater\Helpers\UpdateBackup;
use Okay\Modules\OkayCMS\CoreUpdater\Helpers\UpdateCheckHelper;
use Okay\Modules\OkayCMS\CoreUpdater\Helpers\UpdateDownloader;
use Okay\Modules\OkayCMS\CoreUpdater\Helpers\UpdateRunner;
use Okay\Modules\OkayCMS\CoreUpdater\Helpers\UpdateStatus;
use PHPUnit\Framework\TestCase;

/**
 * Наскрізні тести на handleFailure()/rollback() — приватні, тестуються
 * через Reflection з мокнутими колаборторами, без реального run() (той
 * тягне мережу/лок/composer — Plan E). Мета: спіймати саме ту мутацію,
 * яку не ловить needsRollback()-юніт на літералах (рев'ю Critical 1) —
 * тут needsRollback() рахується всередині ЖИВОГО handleFailure(), а не
 * викликається напряму тестом.
 */
class UpdateRunnerFailureHandlingTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/update-runner-failure-test-' . uniqid('', true);
        mkdir($this->tmpDir . '/config', 0777, true);
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

    private function makeBackupZip(): string
    {
        $path = $this->tmpDir . '/backup.zip';
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE);
        // Порожній ZipArchive (нуль записів) libzip мовчки НЕ пише на диск
        // на close() — той самий підводний камінь, що й у stepBackup().
        $zip->addFromString('.empty', '');
        $zip->close();

        return $path;
    }

    private function writeMaintenanceFlag(): string
    {
        $flagPath = MaintenanceMode::flagPath($this->tmpDir);
        MaintenanceMode::enable($flagPath);

        return $flagPath;
    }

    /**
     * @param list<bool> $healthResponses FIFO-черга відповідей checkHealth() —
     *     реального cURL тут немає, TestableUpdateRunner перевизначає метод.
     */
    private function buildRunner(UpdateApplier $applier, UpdateStatus $status, array $healthResponses): UpdateRunner
    {
        return new TestableUpdateRunner(
            $this->createStub(UpdateCheckHelper::class),
            $status,
            $this->createStub(UpdateDownloader::class),
            $this->createStub(UpdateBackup::class),
            $applier,
            $this->createStub(CoreMigrator::class),
            $this->createStub(Settings::class),
            $this->createStub(Config::class),
            $this->createStub(Design::class),
            $healthResponses
        );
    }

    /** @param array<string, mixed> $state @param array<string, mixed> $ctx @return array<string, mixed> */
    private function invokeHandleFailure(UpdateRunner $runner, array $state, array $ctx, \Throwable $e): array
    {
        // setAccessible() не потрібен з PHP 8.1 — ReflectionMethod::invoke()
        // сам дає доступ до private/protected методів.
        return (new \ReflectionMethod(UpdateRunner::class, 'handleFailure'))->invoke($runner, $state, $ctx, $e);
    }

    // --- Critical 1: needsRollback() мусить дивитись на стан ДО fail() ---

    public function testHandleFailureRollsBackWhenApplyFilesFails(): void
    {
        $backupZip = $this->makeBackupZip();
        $flagPath = $this->writeMaintenanceFlag();

        $applier = $this->createMock(UpdateApplier::class);
        $applier->expects($this->once())->method('restoreFiles')->with($backupZip, $this->tmpDir);

        $status = $this->createMock(UpdateStatus::class);
        $status->expects($this->once())->method('save')->with($this->callback(
            fn (array $saved) => $saved['step'] === UpdateStatus::STEP_ROLLED_BACK
        ));

        $runner = $this->buildRunner($applier, $status, [true]);

        $state = ['step' => UpdateStatus::STEP_APPLY_FILES, 'fromVersion' => '1.0.0', 'toVersion' => '1.1.0', 'updatedAt' => time()];
        $ctx = [
            'rootDir' => $this->tmpDir,
            'backupZipPath' => $backupZip,
            'maintenanceFlagPath' => $flagPath,
            'maintenanceToken' => 'tok',
            'fromVersion' => '1.0.0',
        ];

        $result = $this->invokeHandleFailure($runner, $state, $ctx, new \RuntimeException('apply failed'));

        $this->assertSame(UpdateStatus::STEP_ROLLED_BACK, $result['step']);
        $this->assertArrayNotHasKey('requiresManualIntervention', $result);
        $this->assertFalse(MaintenanceMode::isActive($flagPath), 'maintenance знімається, коли rollback здоровий');
    }

    // --- trace (c): post-apply health-check фейл → rollback-гілка ---

    public function testHandleFailureAfterHealthCheckFailureRollsBack(): void
    {
        $backupZip = $this->makeBackupZip();
        $flagPath = $this->writeMaintenanceFlag();

        $applier = $this->createMock(UpdateApplier::class);
        $applier->expects($this->once())->method('restoreFiles')->with($backupZip, $this->tmpDir);

        $status = $this->createMock(UpdateStatus::class);
        $status->expects($this->once())->method('save');

        $runner = $this->buildRunner($applier, $status, [true]);

        $state = ['step' => UpdateStatus::STEP_HEALTH_CHECK, 'fromVersion' => '1.0.0', 'toVersion' => '1.1.0', 'updatedAt' => time()];
        $ctx = [
            'rootDir' => $this->tmpDir,
            'backupZipPath' => $backupZip,
            'maintenanceFlagPath' => $flagPath,
            'maintenanceToken' => 'tok',
            'fromVersion' => '1.0.0',
        ];

        $result = $this->invokeHandleFailure(
            $runner,
            $state,
            $ctx,
            new \RuntimeException('Health-check після оновлення не підтвердив нову версію 1.1.0.')
        );

        $this->assertSame(UpdateStatus::STEP_ROLLED_BACK, $result['step']);
    }

    // --- trace (d): CoreMigrationException → rolledBackMigrations заповнений ---

    public function testHandleFailureFromMigrationExceptionFillsRolledBackMigrations(): void
    {
        $backupZip = $this->makeBackupZip();
        $flagPath = $this->writeMaintenanceFlag();

        $applier = $this->createMock(UpdateApplier::class);
        $applier->expects($this->once())->method('restoreFiles');

        $status = $this->createMock(UpdateStatus::class);
        $status->expects($this->once())->method('save');

        // Старий health-check не проходить (наприклад, схема вже частково
        // мігрована) — навмисно перевіряє manual-intervention ГІЛКУ поруч
        // із заповненим rolledBackMigrations, а не лише щасливий шлях.
        $runner = $this->buildRunner($applier, $status, [false]);

        $state = ['step' => UpdateStatus::STEP_MIGRATIONS, 'fromVersion' => '1.0.0', 'toVersion' => '1.1.0', 'updatedAt' => time()];
        $ctx = [
            'rootDir' => $this->tmpDir,
            'backupZipPath' => $backupZip,
            'maintenanceFlagPath' => $flagPath,
            'maintenanceToken' => 'tok',
            'fromVersion' => '1.0.0',
        ];

        $exception = new CoreMigrationException('Core-міграція впала', ['1.1.0_add_col.up.sql']);

        $result = $this->invokeHandleFailure($runner, $state, $ctx, $exception);

        $this->assertSame(UpdateStatus::STEP_ROLLED_BACK, $result['step']);
        $this->assertSame(['1.1.0_add_col.up.sql'], $result['rolledBackMigrations']);
        $this->assertTrue($result['requiresManualIntervention']);
        $this->assertTrue(MaintenanceMode::isActive($flagPath), 'нездоровий rollback не знімає maintenance');
    }

    // --- Critical 2: голий \RuntimeException з restoreFiles не має вилітати неспійманим ---

    public function testHandleFailureSurvivesBareRuntimeExceptionFromRestoreFiles(): void
    {
        $backupZip = $this->makeBackupZip();
        $flagPath = $this->writeMaintenanceFlag();

        $applier = $this->createMock(UpdateApplier::class);
        // ZipArchive::open() на битому архіві в UpdateApplier::restoreFiles()
        // кидає ГОЛИЙ \RuntimeException, не UpdateApplyException.
        $applier->expects($this->once())->method('restoreFiles')->willThrowException(new \RuntimeException('archive is corrupt'));

        $status = $this->createMock(UpdateStatus::class);
        $status->expects($this->once())->method('save');

        $runner = $this->buildRunner($applier, $status, [false]);

        $state = ['step' => UpdateStatus::STEP_APPLY_FILES, 'fromVersion' => '1.0.0', 'toVersion' => '1.1.0', 'updatedAt' => time()];
        $ctx = [
            'rootDir' => $this->tmpDir,
            'backupZipPath' => $backupZip,
            'maintenanceFlagPath' => $flagPath,
            'maintenanceToken' => 'tok',
            'fromVersion' => '1.0.0',
        ];

        $result = $this->invokeHandleFailure($runner, $state, $ctx, new \RuntimeException('apply failed'));

        $this->assertSame(UpdateStatus::STEP_ROLLED_BACK, $result['step']);
        $this->assertSame('archive is corrupt', $result['restoreError']);
        $this->assertTrue($result['requiresManualIntervention']);
    }

    // --- Minor 4: провал ДО apply_files не тримає maintenance навічно ---

    public function testHandleFailureBeforeApplyFilesDisablesDanglingMaintenanceFlag(): void
    {
        $flagPath = $this->writeMaintenanceFlag();

        $applier = $this->createMock(UpdateApplier::class);
        $applier->expects($this->never())->method('restoreFiles');

        $status = $this->createMock(UpdateStatus::class);
        $status->expects($this->once())->method('save')->with($this->callback(
            fn (array $saved) => $saved['step'] === UpdateStatus::STEP_FAILED
        ));

        $runner = $this->buildRunner($applier, $status, []);

        $state = ['step' => UpdateStatus::STEP_MAINTENANCE_ON, 'fromVersion' => '1.0.0', 'toVersion' => '1.1.0', 'updatedAt' => time()];
        $ctx = ['rootDir' => $this->tmpDir, 'maintenanceFlagPath' => $flagPath, 'maintenanceToken' => 'tok'];

        $result = $this->invokeHandleFailure($runner, $state, $ctx, new \RuntimeException('maintenance write failed'));

        $this->assertSame(UpdateStatus::STEP_FAILED, $result['step']);
        $this->assertTrue($result['maintenanceDisabledAfterFailure']);
        $this->assertFalse(MaintenanceMode::isActive($flagPath));
    }

    public function testHandleFailureBeforeMaintenanceOnLeavesNoFlagToDisable(): void
    {
        $applier = $this->createMock(UpdateApplier::class);
        $applier->expects($this->never())->method('restoreFiles');

        $status = $this->createMock(UpdateStatus::class);
        $status->expects($this->once())->method('save');

        $runner = $this->buildRunner($applier, $status, []);

        $state = ['step' => UpdateStatus::STEP_PREFLIGHT, 'fromVersion' => '1.0.0', 'toVersion' => '1.1.0', 'updatedAt' => time()];
        $ctx = ['rootDir' => $this->tmpDir];

        $result = $this->invokeHandleFailure($runner, $state, $ctx, new \RuntimeException('preflight failed'));

        $this->assertSame(UpdateStatus::STEP_FAILED, $result['step']);
        $this->assertArrayNotHasKey('maintenanceDisabledAfterFailure', $result);
    }
}

/**
 * checkHealth() б'є в мережу — для юніт-тестів на handleFailure()/rollback()
 * підміняємо його канонічною чергою відповідей замість реального cURL.
 */
class TestableUpdateRunner extends UpdateRunner
{
    /** @var list<bool> */
    private array $healthResponses;

    /** @param list<bool> $healthResponses */
    public function __construct(
        UpdateCheckHelper $checkHelper,
        UpdateStatus $status,
        UpdateDownloader $downloader,
        UpdateBackup $backup,
        UpdateApplier $applier,
        CoreMigrator $migrator,
        Settings $settings,
        Config $config,
        Design $design,
        array $healthResponses
    ) {
        parent::__construct($checkHelper, $status, $downloader, $backup, $applier, $migrator, $settings, $config, $design);
        $this->healthResponses = $healthResponses;
    }

    public function checkHealth(string $token, string $expectedForkVersion, ?string $rootUrl = null): bool
    {
        if ($this->healthResponses === []) {
            throw new \LogicException('TestableUpdateRunner: черга healthResponses вичерпана.');
        }

        return array_shift($this->healthResponses);
    }
}
