<?php

namespace Core\Update;

use Okay\Core\Config;
use Okay\Core\Design;
use Okay\Core\Release\CoreMigrator;
use Okay\Core\Release\PackageBuilder;
use Okay\Core\Settings;
use Okay\Core\Update\UpdateApplier;
use Okay\Core\Update\UpdateBackup;
use Okay\Core\Update\UpdateCheckHelper;
use Okay\Core\Update\UpdateDownloader;
use Okay\Core\Update\UpdateRunner;
use Okay\Core\Update\UpdateStatus;
use PHPUnit\Framework\TestCase;

/**
 * Покоління пакета й вибір наступного релізу з боку UpdateRunner: справжній
 * пакет, зібраний PackageBuilder, проходить verify лише зі своїм поколінням.
 */
class UpdateRunnerGenerationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/runner-generation-' . uniqid();
        mkdir($this->root . '/out', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->root);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($dir);
    }

    private function runner(?UpdateCheckHelper $checkHelper = null): UpdateRunner
    {
        return new UpdateRunner(
            $checkHelper ?? $this->createStub(UpdateCheckHelper::class),
            $this->createStub(UpdateStatus::class),
            new UpdateDownloader($this->createStub(Config::class)),
            $this->createStub(UpdateBackup::class),
            $this->createStub(UpdateApplier::class),
            $this->createStub(CoreMigrator::class),
            $this->createStub(Settings::class),
            $this->createStub(Config::class),
            $this->createStub(Design::class)
        );
    }

    /** @return array{zipPath: string, checksumsPath: string} */
    private function buildPackage(int $format, ?bool $withAdminTheme = null): array
    {
        $withAdminTheme ??= $format > 1;
        $manifest = $this->root . '/manifest.json';
        file_put_contents($manifest, json_encode([
            'packageFormat' => $format,
            'include' => ['Okay/Core/', 'backend/'],
            'exclude' => $withAdminTheme ? ['backend/design/compiled/'] : ['backend/design/'],
        ]));

        return (new PackageBuilder())->build(dirname(__DIR__) . '/Release/fixtures/sample-repo', $manifest, '1.4.0', '4.5.3', $this->root . '/out');
    }

    /** @param array<string, mixed> $ctx */
    private function verify(array $ctx): array
    {
        return (new \ReflectionMethod(UpdateRunner::class, 'stepVerify'))->invoke($this->runner(), $ctx);
    }

    private function ctx(array $package, int $assetFormat): array
    {
        return [
            'rootDir' => $this->root,
            'toVersion' => '1.4.0',
            'zipPath' => $package['zipPath'],
            'checksumsPath' => $package['checksumsPath'],
            'latest' => ['assets' => ['packageFormat' => $assetFormat]],
        ];
    }

    public function testPackageOfTheAnnouncedGenerationPassesVerify(): void
    {
        $ctx = $this->verify($this->ctx($this->buildPackage(2), 2));

        $this->assertSame(2, $ctx['versionMeta']['packageFormat']);
        $this->assertArrayHasKey('backend/design/theme.tpl', $ctx['manifestFiles']);
    }

    /**
     * Архів другого покоління, перейменований на назву першого, — те, що
     * побачили б старі сайти. Межу задає version.json, і розбіжність з назвою
     * зупиняє прогін ще до бекапу.
     */
    public function testRenamedArchiveIsRefused(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('покоління 1');

        $this->verify($this->ctx($this->buildPackage(2), 1));
    }

    public function testFirstGenerationPackageStillPasses(): void
    {
        $ctx = $this->verify($this->ctx($this->buildPackage(1), 1));

        $this->assertArrayNotHasKey('backend/design/theme.tpl', $ctx['manifestFiles']);
    }

    /**
     * Пакет першого покоління з темою адмінки відхилили б сайти до 1.3.12;
     * verify нового коду відхиляє його так само — межу задає покоління пакета.
     */
    public function testFirstGenerationPackageWithTheAdminThemeIsRefused(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('backend/design/theme.tpl');

        $this->verify($this->ctx($this->buildPackage(1, true), 1));
    }

    /**
     * Знімок, вибраний для іншої версії (до оновлення на міст), вказує на
     * реліз, що вже стоїть. Прогін на ньому не стартує.
     */
    public function testRunRefusesASnapshotChosenForAnotherVersion(): void
    {
        $installed = (new \ReflectionClass(Config::class))->getDefaultProperties()['forkVersion'];
        $checkHelper = $this->createStub(UpdateCheckHelper::class);
        $checkHelper->method('check')->willReturn([
            'latestFor' => '0.0.1',
            'latest' => ['forkVersion' => '99.0.0', 'assets' => []],
        ]);

        $this->assertNotSame('0.0.1', $installed);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('який реліз наступний');

        (new \ReflectionMethod(UpdateRunner::class, 'runLocked'))->invoke($this->runner($checkHelper), $this->root, null);
    }
}
