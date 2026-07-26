<?php

namespace Security;

use PHPUnit\Framework\TestCase;

class UnserializeHardeningTest extends TestCase
{
    public function testNoCallSiteUnserializesWithoutAnAllowedClassesOption()
    {
        $root = dirname(__DIR__, 2);
        $offenders = [];

        foreach ($this->scannedDirs() as $dir) {
            foreach ($this->phpFiles($root . '/' . $dir) as $file) {
                $source = file_get_contents($file);
                if ($source === false) {
                    continue;
                }

                // Только вызовы глобальной функции: объявления и вызовы
                // собственных методов-обёрток ($this->unserialize) не считаются.
                if (!preg_match_all(
                    '/(?<![\w>:$])(?<!function )unserialize\s*\(/',
                    $source,
                    $matches,
                    PREG_OFFSET_CAPTURE
                )) {
                    continue;
                }

                foreach ($matches[0] as $match) {
                    $tail = substr($source, $match[1], 300);
                    if (strpos($tail, 'allowed_classes') === false) {
                        $offenders[] = str_replace($root . '/', '', $file);
                        break;
                    }
                }
            }
        }

        $this->assertSame([], array_values(array_unique($offenders)));
    }

    public function testLicenseStorageAllowsOnlyItsOwnDto()
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/Okay/Core/Modules/LicenseStorage.php'
        );
        $this->assertIsString($source);

        // Здесь объект нужен по делу, поэтому список классов, а не false
        $this->assertStringContainsString("'allowed_classes' => [LicenseDTO::class]", $source);
    }

    public function testIntegration1cResolvesFilenamesThroughThePathResolver()
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/Okay/Modules/OkayCMS/Integration1C/Controllers/Integration1cController.php'
        );
        $this->assertIsString($source);

        $this->assertStringContainsString('isSafeRelativePath', $source);
    }

    private function scannedDirs()
    {
        return ['Okay', 'backend'];
    }

    private function phpFiles($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                yield $file->getPathname();
            }
        }
    }
}
