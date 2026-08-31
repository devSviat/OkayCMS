<?php

namespace Modules\Sviat\CoreUpdater;

use Okay\Modules\Sviat\CoreUpdater\Helpers\UpdateApplier;
use Okay\Modules\Sviat\CoreUpdater\Helpers\UpdateApplyException;
use Okay\Modules\Sviat\CoreUpdater\Helpers\UpdateBackup;
use PHPUnit\Framework\TestCase;

class UpdateApplierTest extends TestCase
{
    private string $tmpDir;
    private UpdateApplier $applier;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/update-applier-test-' . uniqid('', true);
        mkdir($this->tmpDir, 0777, true);
        $this->applier = new UpdateApplier();
    }

    protected function tearDown(): void
    {
        // Права read-only з тестів на збій треба зняти назад, інакше
        // removeDir() не зможе прибрати за собою.
        $this->chmodRecursive($this->tmpDir, 0777);
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

    private function chmodRecursive(string $dir, int $mode): void
    {
        if (!is_dir($dir)) {
            return;
        }

        chmod($dir, $mode);
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->chmodRecursive($path, $mode) : chmod($path, $mode);
        }
    }

    private function writeFile(string $path, string $content): void
    {
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, $content);
    }

    // --- applyFiles ---

    public function testApplyFilesCopiesNewFileIntoTargetTree(): void
    {
        $payloadDir = $this->tmpDir . '/payload';
        $rootDir = $this->tmpDir . '/root';
        $this->writeFile($payloadDir . '/Okay/Core/New.php', '<?php // new');

        $applied = $this->applier->applyFiles($payloadDir, $rootDir, ['Okay/Core/New.php' => 'hash']);

        $this->assertSame(['Okay/Core/New.php'], $applied);
        $this->assertSame('<?php // new', file_get_contents($rootDir . '/Okay/Core/New.php'));
    }

    public function testApplyFilesReplacesExistingFileContent(): void
    {
        $payloadDir = $this->tmpDir . '/payload';
        $rootDir = $this->tmpDir . '/root';
        $this->writeFile($payloadDir . '/Okay/Core/Existing.php', '<?php // updated');
        $this->writeFile($rootDir . '/Okay/Core/Existing.php', '<?php // old');

        $this->applier->applyFiles($payloadDir, $rootDir, ['Okay/Core/Existing.php' => 'hash']);

        $this->assertSame('<?php // updated', file_get_contents($rootDir . '/Okay/Core/Existing.php'));
    }

    public function testApplyFilesKeepsExecutableBitOfReplacedFile(): void
    {
        $payloadDir = $this->tmpDir . '/payload';
        $rootDir = $this->tmpDir . '/root';
        $this->writeFile($payloadDir . '/ok', '#!/usr/bin/env php updated');
        $this->writeFile($rootDir . '/ok', '#!/usr/bin/env php old');
        chmod($rootDir . '/ok', 0755);

        $this->applier->applyFiles($payloadDir, $rootDir, ['ok' => 'hash']);

        $this->assertTrue(is_executable($rootDir . '/ok'));
        $this->assertSame('#!/usr/bin/env php updated', file_get_contents($rootDir . '/ok'));
    }

    public function testApplyFilesCreatesNestedDirectoriesAsNeeded(): void
    {
        $payloadDir = $this->tmpDir . '/payload';
        $rootDir = $this->tmpDir . '/root';
        $this->writeFile($payloadDir . '/Okay/Modules/Vendor/Module/Init/Init.php', '<?php // module');

        $applied = $this->applier->applyFiles(
            $payloadDir,
            $rootDir,
            ['Okay/Modules/Vendor/Module/Init/Init.php' => 'hash']
        );

        $this->assertSame(['Okay/Modules/Vendor/Module/Init/Init.php'], $applied);
        $this->assertSame(
            '<?php // module',
            file_get_contents($rootDir . '/Okay/Modules/Vendor/Module/Init/Init.php')
        );
    }

    public function testApplyFilesLeavesFilesNotInManifestUntouched(): void
    {
        $payloadDir = $this->tmpDir . '/payload';
        $rootDir = $this->tmpDir . '/root';
        $this->writeFile($payloadDir . '/Okay/Core/New.php', '<?php // new');
        $this->writeFile($rootDir . '/Okay/Core/Untouched.php', '<?php // keep me');

        $this->applier->applyFiles($payloadDir, $rootDir, ['Okay/Core/New.php' => 'hash']);

        $this->assertSame('<?php // keep me', file_get_contents($rootDir . '/Okay/Core/Untouched.php'));
    }

    public function testApplyFilesDoesNotLeaveTmpFileBehindOnSuccess(): void
    {
        $payloadDir = $this->tmpDir . '/payload';
        $rootDir = $this->tmpDir . '/root';
        $this->writeFile($payloadDir . '/Okay/Core/New.php', '<?php // new');

        $this->applier->applyFiles($payloadDir, $rootDir, ['Okay/Core/New.php' => 'hash']);

        $this->assertFileDoesNotExist($rootDir . '/Okay/Core/New.php.core-update.tmp');
    }

    public function testApplyFilesInvokesOnProgressForEachAppliedFile(): void
    {
        $payloadDir = $this->tmpDir . '/payload';
        $rootDir = $this->tmpDir . '/root';
        $this->writeFile($payloadDir . '/A.php', '<?php // a');
        $this->writeFile($payloadDir . '/B.php', '<?php // b');

        $seen = [];
        $this->applier->applyFiles(
            $payloadDir,
            $rootDir,
            ['A.php' => 'hash', 'B.php' => 'hash'],
            function (string $relativePath) use (&$seen): void {
                $seen[] = $relativePath;
            }
        );

        $this->assertSame(['A.php', 'B.php'], $seen);
    }

    public function testApplyFilesThrowsWithAppliedListSoFarWhenSourceFileIsMissing(): void
    {
        $payloadDir = $this->tmpDir . '/payload';
        $rootDir = $this->tmpDir . '/root';
        $this->writeFile($payloadDir . '/A.php', '<?php // a');
        // B.php заявлений у manifest, але фізично відсутній у payload.

        try {
            $this->applier->applyFiles($payloadDir, $rootDir, ['A.php' => 'hash', 'B.php' => 'hash']);
            $this->fail('Очікувався UpdateApplyException.');
        } catch (UpdateApplyException $e) {
            $this->assertSame(['A.php'], $e->appliedPaths);
        }

        $this->assertSame('<?php // a', file_get_contents($rootDir . '/A.php'));
    }

    public function testApplyFilesThrowsAndCleansUpTmpFileWhenTargetDirectoryCannotBeCreated(): void
    {
        $payloadDir = $this->tmpDir . '/payload';
        $rootDir = $this->tmpDir . '/root';
        $this->writeFile($payloadDir . '/A.php', '<?php // a');
        $this->writeFile($payloadDir . '/Okay/Core/Locked.php', '<?php // locked');
        // Ціль для mkdir() зайнята звичайним файлом — детермінований провал
        // незалежно від прав доступу (chmod 0555 нічого не значить під root,
        // під яким тут виконуються тести всередині контейнера).
        $this->writeFile($rootDir . '/Okay/Core', 'blocking file, not a directory');

        try {
            $this->applier->applyFiles(
                $payloadDir,
                $rootDir,
                ['A.php' => 'hash', 'Okay/Core/Locked.php' => 'hash']
            );
            $this->fail('Очікувався UpdateApplyException.');
        } catch (UpdateApplyException $e) {
            $this->assertSame(['A.php'], $e->appliedPaths);
        }

        $this->assertFileDoesNotExist($rootDir . '/Okay/Core/Locked.php.core-update.tmp');
    }

    // --- restoreFiles ---

    public function testRestoreFilesWritesBackByteExactContentFromBackupZip(): void
    {
        $rootDir = $this->tmpDir . '/root';
        $this->writeFile($rootDir . '/Okay/Core/Foo.php', '<?php // updated, will be reverted');

        $backupZipPath = $this->tmpDir . '/backup.zip';
        $zip = new \ZipArchive();
        $zip->open($backupZipPath, \ZipArchive::CREATE);
        $zip->addFromString('Okay/Core/Foo.php', "<?php\n// original\nbinary: \x00\x01\xFF");
        $zip->close();

        $restored = $this->applier->restoreFiles($backupZipPath, $rootDir);

        $this->assertSame(['Okay/Core/Foo.php'], $restored);
        $this->assertSame(
            "<?php\n// original\nbinary: \x00\x01\xFF",
            file_get_contents($rootDir . '/Okay/Core/Foo.php')
        );
    }

    public function testRestoreFilesRecreatesMissingDirectoriesForRemovedFiles(): void
    {
        $rootDir = $this->tmpDir . '/root';
        mkdir($rootDir, 0777, true);

        $backupZipPath = $this->tmpDir . '/backup.zip';
        $zip = new \ZipArchive();
        $zip->open($backupZipPath, \ZipArchive::CREATE);
        $zip->addFromString('Okay/Core/Gone.php', '<?php // restored');
        $zip->close();

        $restored = $this->applier->restoreFiles($backupZipPath, $rootDir);

        $this->assertSame(['Okay/Core/Gone.php'], $restored);
        $this->assertSame('<?php // restored', file_get_contents($rootDir . '/Okay/Core/Gone.php'));
    }

    public function testRestoreFilesSkipsEmptyBackupMarkerAndRestoresOnlyRealFiles(): void
    {
        $rootDir = $this->tmpDir . '/root';

        $backupZipPath = $this->tmpDir . '/backup.zip';
        $zip = new \ZipArchive();
        $zip->open($backupZipPath, \ZipArchive::CREATE);
        // Той самий маркер, яким UpdateRunner::stepBackup() змушує libzip
        // реально записати порожній backup-архів на диск (спорожнілий
        // ZipArchive без записів libzip мовчки не пише файл узагалі).
        $zip->addFromString(UpdateBackup::EMPTY_BACKUP_MARKER, '');
        $zip->addFromString('Okay/Core/Foo.php', '<?php // restored');
        $zip->close();

        $restored = $this->applier->restoreFiles($backupZipPath, $rootDir);

        $this->assertSame(['Okay/Core/Foo.php'], $restored);
        $this->assertSame('<?php // restored', file_get_contents($rootDir . '/Okay/Core/Foo.php'));
        $this->assertFileDoesNotExist($rootDir . '/' . UpdateBackup::EMPTY_BACKUP_MARKER);
    }

    public function testRestoreFilesThrowsWithRestoredListSoFarOnFailure(): void
    {
        $rootDir = $this->tmpDir . '/root';
        // Ціль для mkdir() зайнята звичайним файлом — детермінований провал
        // незалежно від прав доступу (root під яким виконуються тести не
        // зупинить chmod-based read-only перевірку).
        $this->writeFile($rootDir . '/Okay/Core', 'blocking file, not a directory');

        $backupZipPath = $this->tmpDir . '/backup.zip';
        $zip = new \ZipArchive();
        $zip->open($backupZipPath, \ZipArchive::CREATE);
        $zip->addFromString('A.php', '<?php // a');
        $zip->addFromString('Okay/Core/Locked.php', '<?php // locked');
        $zip->close();

        try {
            $this->applier->restoreFiles($backupZipPath, $rootDir);
            $this->fail('Очікувався UpdateApplyException.');
        } catch (UpdateApplyException $e) {
            $this->assertSame(['A.php'], $e->appliedPaths);
        }
    }

    // --- runComposerIfNeeded ---

    public function testRunComposerIfNeededReturnsNullWhenPackageHasNoComposerLock(): void
    {
        $payloadDir = $this->tmpDir . '/payload';
        $rootDir = $this->tmpDir . '/root';
        mkdir($payloadDir, 0777, true);
        mkdir($rootDir, 0777, true);

        $this->assertNull($this->applier->runComposerIfNeeded($rootDir, $payloadDir));
    }

    public function testRunComposerIfNeededReturnsNullWhenLockContentIsIdentical(): void
    {
        $payloadDir = $this->tmpDir . '/payload';
        $rootDir = $this->tmpDir . '/root';
        $lockContent = '{"content-hash": "abc"}';
        $this->writeFile($payloadDir . '/composer.lock', $lockContent);
        $this->writeFile($rootDir . '/composer.lock', $lockContent);

        // mtime навмисно різний — порівняння має бути за вмістом, не mtime.
        touch($payloadDir . '/composer.lock', time() - 100000);

        $this->assertNull($this->applier->runComposerIfNeeded($rootDir, $payloadDir));
    }

    public function testRunComposerIfNeededThrowsWhenLockDiffersAndComposerIsUnavailable(): void
    {
        $payloadDir = $this->tmpDir . '/payload';
        $rootDir = $this->tmpDir . '/root';
        $this->writeFile($payloadDir . '/composer.lock', '{"content-hash": "new"}');
        $this->writeFile($rootDir . '/composer.lock', '{"content-hash": "old"}');

        $this->withEmptyPath(function () use ($rootDir, $payloadDir): void {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessageMatches('/composer.*не знайдено/u');
            $this->applier->runComposerIfNeeded($rootDir, $payloadDir);
        });
    }

    public function testRunComposerIfNeededTreatsMissingCurrentLockAsDifferent(): void
    {
        $payloadDir = $this->tmpDir . '/payload';
        $rootDir = $this->tmpDir . '/root';
        $this->writeFile($payloadDir . '/composer.lock', '{"content-hash": "new"}');
        mkdir($rootDir, 0777, true);
        // Кореневого composer.lock немає взагалі.

        $this->withEmptyPath(function () use ($rootDir, $payloadDir): void {
            $this->expectException(\RuntimeException::class);
            $this->applier->runComposerIfNeeded($rootDir, $payloadDir);
        });
    }

    /**
     * Symfony\Process::getDefaultEnv() бере PATH передусім із $_ENV
     * (вищий пріоритет за getenv()), тож самого putenv() недостатньо, щоб
     * composer/composer.phar стали "недоступні" для дочірнього процесу —
     * треба підмінити всі три джерела разом.
     */
    private function withEmptyPath(callable $body): void
    {
        $emptyDir = $this->tmpDir . '/empty-path-' . uniqid('', true);
        mkdir($emptyDir, 0777, true);

        $originalEnvPath = $_ENV['PATH'] ?? null;
        $originalServerPath = $_SERVER['PATH'] ?? null;
        $originalGetenvPath = getenv('PATH');

        $_ENV['PATH'] = $emptyDir;
        $_SERVER['PATH'] = $emptyDir;
        putenv('PATH=' . $emptyDir);

        try {
            $body();
        } finally {
            if ($originalEnvPath === null) {
                unset($_ENV['PATH']);
            } else {
                $_ENV['PATH'] = $originalEnvPath;
            }

            if ($originalServerPath === null) {
                unset($_SERVER['PATH']);
            } else {
                $_SERVER['PATH'] = $originalServerPath;
            }

            putenv($originalGetenvPath === false ? 'PATH' : 'PATH=' . $originalGetenvPath);
        }
    }

    // --- clearCaches ---

    public function testClearCachesCallsClearCompiledOnDesign(): void
    {
        $design = $this->createMock(\Okay\Core\Design::class);
        $design->expects($this->once())->method('clearCompiled');

        $this->applier->clearCaches($design);
    }
}
