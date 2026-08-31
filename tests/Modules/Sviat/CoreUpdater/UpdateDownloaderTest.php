<?php

namespace Modules\Sviat\CoreUpdater;

use Okay\Core\Update\UpdateDownloader;
use PHPUnit\Framework\TestCase;

class UpdateDownloaderTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/update-downloader-test-' . uniqid('', true);
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . '/*') ?: [] as $f) {
            is_dir($f) ? $this->removeDir($f) : unlink($f);
        }
        @rmdir($this->tmpDir);
    }

    private function removeDir(string $dir): void
    {
        foreach (scandir($dir) as $e) {
            if ($e === '.' || $e === '..') {
                continue;
            }
            $p = $dir . '/' . $e;
            is_dir($p) && !is_link($p) ? $this->removeDir($p) : unlink($p);
        }
        rmdir($dir);
    }

    public function testEntryNameSafeAcceptsOrdinaryPaths(): void
    {
        UpdateDownloader::assertEntryNameSafe('payload/Okay/Core/Config.php');
        UpdateDownloader::assertEntryNameSafe('payload/');
        $this->expectNotToPerformAssertions();
    }

    public function testEntryNameSafeRejectsParentTraversal(): void
    {
        $this->expectException(\RuntimeException::class);
        UpdateDownloader::assertEntryNameSafe('payload/../../etc/passwd');
    }

    public function testEntryNameSafeRejectsBackslashTraversal(): void
    {
        $this->expectException(\RuntimeException::class);
        UpdateDownloader::assertEntryNameSafe('payload\\..\\..\\Okay\\evil.php');
    }

    public function testEntryNameSafeRejectsAbsolutePath(): void
    {
        $this->expectException(\RuntimeException::class);
        UpdateDownloader::assertEntryNameSafe('/etc/cron.d/evil');
    }

    public function testEntryNameSafeRejectsWindowsAbsolutePath(): void
    {
        $this->expectException(\RuntimeException::class);
        UpdateDownloader::assertEntryNameSafe('C:/Windows/System32/evil.dll');
    }

    public function testEntryNameSafeRejectsNullByte(): void
    {
        $this->expectException(\RuntimeException::class);
        UpdateDownloader::assertEntryNameSafe("payload/ok.php\0.png");
    }

    public function testExtractRejectsArchiveWithSymlinkEntry(): void
    {
        $zipPath = $this->tmpDir . '/evil.zip';
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($zipPath, \ZipArchive::CREATE) === true);
        $zip->addFromString('payload/link', '/etc/passwd');
        // Позначити запис симлінком: S_IFLNK (0xA000) | 0777, у старших 16 бітах.
        $zip->setExternalAttributesName('payload/link', \ZipArchive::OPSYS_UNIX, (0xA000 | 0777) << 16);
        $zip->close();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/symlink/i');
        $this->downloader()->extract($zipPath, $this->tmpDir . '/out');
    }

    public function testExtractRejectsArchiveWithTraversalEntry(): void
    {
        $zipPath = $this->tmpDir . '/traversal.zip';
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($zipPath, \ZipArchive::CREATE) === true);
        $zip->addFromString('payload/../../escaped.php', '<?php // escaped');
        $zip->close();

        $this->expectException(\RuntimeException::class);
        $this->downloader()->extract($zipPath, $this->tmpDir . '/out');
    }

    public function testExtractAcceptsCleanArchive(): void
    {
        $zipPath = $this->tmpDir . '/clean.zip';
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($zipPath, \ZipArchive::CREATE) === true);
        $zip->addFromString('payload/Okay/Core/Config.php', '<?php // ok');
        $zip->close();

        $this->downloader()->extract($zipPath, $this->tmpDir . '/out');

        $this->assertSame('<?php // ok', file_get_contents($this->tmpDir . '/out/payload/Okay/Core/Config.php'));
    }

    private function downloader(): UpdateDownloader
    {
        // extract() не читає Config; конструктор Config вимагає файли, тож
        // створюємо об'єкт downloader без виклику його конструктора.
        return (new \ReflectionClass(UpdateDownloader::class))->newInstanceWithoutConstructor();
    }
}
