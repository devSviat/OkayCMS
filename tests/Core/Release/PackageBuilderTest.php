<?php

namespace Core\Release;

use Okay\Core\Release\PackageBuilder;
use PHPUnit\Framework\TestCase;

class PackageBuilderTest extends TestCase
{
    private string $fixturesDir;
    private string $stagingDir;

    protected function setUp(): void
    {
        $this->fixturesDir = __DIR__ . '/fixtures';
        $this->stagingDir = sys_get_temp_dir() . '/package-builder-test-' . uniqid();
        mkdir($this->stagingDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->stagingDir);
    }

    public function testStageCopiesIncludedFilesAndWritesMetadata(): void
    {
        $builder = new PackageBuilder();

        $result = $builder->stage(
            $this->fixturesDir . '/sample-repo',
            $this->fixturesDir . '/sample-manifest.json',
            '1.1.0',
            '4.6.0',
            $this->stagingDir
        );

        $this->assertSame(3, $result['fileCount']);
        $this->assertSame(0, $result['migrationsCount']);
        $this->assertFalse($result['requiresMigrations']);

        $this->assertFileExists($this->stagingDir . '/payload/Okay/Core/Foo.php');
        $this->assertFileExists($this->stagingDir . '/payload/Okay/Core/Bar.php');
        $this->assertFileExists($this->stagingDir . '/payload/backend/Controller.php');
        $this->assertFileDoesNotExist($this->stagingDir . '/payload/backend/design/theme.tpl');

        $version = json_decode(file_get_contents($this->stagingDir . '/version.json'), true);
        $this->assertSame('1.1.0', $version['forkVersion']);
        $this->assertSame('4.6.0', $version['upstreamBase']);
        $this->assertSame('^8.4', $version['minPhp']);
        $this->assertFalse($version['requiresMigrations']);
        $this->assertNotEmpty($version['releasedAt']);

        $manifest = json_decode(file_get_contents($this->stagingDir . '/manifest.json'), true);
        $this->assertArrayHasKey('Okay/Core/Foo.php', $manifest['files']);
        $this->assertSame(
            hash_file('sha256', $this->fixturesDir . '/sample-repo/Okay/Core/Foo.php'),
            $manifest['files']['Okay/Core/Foo.php']
        );
        $this->assertArrayNotHasKey('backend/design/theme.tpl', $manifest['files']);
    }

    public function testStageBundlesPendingMigrationsWhenPresent(): void
    {
        $migrationsSource = $this->stagingDir . '/../pending-migrations-' . uniqid();
        mkdir($migrationsSource, 0777, true);
        file_put_contents($migrationsSource . '/1.1.0_add_column.up.sql', 'ALTER TABLE ok_foo ADD COLUMN bar INT;');

        $builder = new PackageBuilder();

        try {
            $result = $builder->stage(
                $this->fixturesDir . '/sample-repo',
                $this->fixturesDir . '/sample-manifest.json',
                '1.1.0',
                '4.6.0',
                $this->stagingDir,
                $migrationsSource
            );

            $this->assertSame(1, $result['migrationsCount']);
            $this->assertTrue($result['requiresMigrations']);
            $this->assertFileExists($this->stagingDir . '/migrations/1.1.0_add_column.up.sql');
        } finally {
            $this->removeDirectory($migrationsSource);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($dir);
    }
}
