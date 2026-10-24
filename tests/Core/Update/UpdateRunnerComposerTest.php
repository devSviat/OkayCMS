<?php

namespace Core\Update;

use Okay\Core\Config;
use Okay\Core\Design;
use Okay\Core\Release\CoreMigrator;
use Okay\Core\Settings;
use Okay\Core\Update\MaintenanceMode;
use Okay\Core\Update\UpdateApplier;
use Okay\Core\Update\UpdateBackup;
use Okay\Core\Update\UpdateCheckHelper;
use Okay\Core\Update\UpdateDownloader;
use Okay\Core\Update\UpdateRunner;
use Okay\Core\Update\UpdateStatus;
use PHPUnit\Framework\TestCase;

/**
 * composer install в оновленні. До 1.4.1 рішення ухвалювалось після підміни
 * файлів, коли composer.lock сайту вже пакетний, і composer не запускався
 * ніколи: нові залежності (bacon/bacon-qr-code в 1.4.0) не встановлювались.
 */
class UpdateRunnerComposerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/runner-composer-' . uniqid();
        mkdir($this->root . '/files/tmp', 0777, true);
        mkdir($this->root . '/config', 0777, true);
        mkdir($this->root . '/vendor/composer', 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    private function runner(UpdateApplier $applier): UpdateRunner
    {
        $config = $this->createStub(Config::class);
        $config->method('get')->willReturnMap([['root_dir', $this->root]]);

        return new UpdateRunner(
            $this->createStub(UpdateCheckHelper::class),
            $this->createStub(UpdateStatus::class),
            $this->createStub(UpdateDownloader::class),
            $this->createStub(UpdateBackup::class),
            $applier,
            $this->createStub(CoreMigrator::class),
            $this->createStub(Settings::class),
            $config,
            $this->createStub(Design::class)
        );
    }

    private function applyFiles(UpdateApplier $applier, array $ctx): array
    {
        return (new \ReflectionMethod(UpdateRunner::class, 'stepApplyFiles'))->invoke($this->runner($applier), $ctx + [
            'rootDir' => $this->root,
            'extractDir' => $this->root . '/extracted',
            'manifestFiles' => [],
            'toVersion' => '1.4.1',
        ]);
    }

    /** Крок підміни запускає composer за рішенням preflight, а не перераховує його після підміни. */
    public function testApplyStepRunsComposerWhenPreflightSaidSo(): void
    {
        $applier = $this->createMock(UpdateApplier::class);
        $applier->method('applyFiles')->willReturn([]);
        $applier->expects($this->once())->method('runComposer')->with($this->root)->willReturn('ok');
        $applier->expects($this->never())->method('runComposerIfNeeded');

        $this->assertSame('ok', $this->applyFiles($applier, ['composerRequired' => true])['composerOutput']);
    }

    public function testApplyStepSkipsComposerWhenNotRequired(): void
    {
        $applier = $this->createMock(UpdateApplier::class);
        $applier->method('applyFiles')->willReturn([]);
        $applier->expects($this->never())->method('runComposer');

        $this->assertArrayNotHasKey('composerOutput', $this->applyFiles($applier, ['composerRequired' => false]));
    }

    /** Поки composer міняє vendor/ — технічні роботи; після, навіть при збої, — знято. */
    public function testInstallDependenciesHoldsMaintenanceOnlyWhileComposerRuns(): void
    {
        $flag = MaintenanceMode::flagPath($this->root);
        $applier = $this->createMock(UpdateApplier::class);
        $applier->expects($this->once())->method('runComposer')->willReturnCallback(function () use ($flag): string {
            $this->assertFileExists($flag, 'composer працює під технічними роботами');
            throw new \RuntimeException('composer впав');
        });

        try {
            $this->runner($applier)->installDependencies();
            $this->fail('збій composer мав дійти до виклику');
        } catch (\RuntimeException $e) {
            $this->assertSame('composer впав', $e->getMessage());
        }
        $this->assertFileDoesNotExist($flag);
    }
}
