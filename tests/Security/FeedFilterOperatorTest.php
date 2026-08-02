<?php

namespace Security;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class FeedFilterOperatorTest extends TestCase
{
    #[DataProvider('runtimeAdapterProvider')]
    public function testRuntimeAdaptersNormalizeOperators($file)
    {
        $source = $this->read($file);

        $this->assertStringContainsString('normalizeComparisonOperator', $source, $file);
        $this->assertStringNotContainsString(
            "\$operator = \$this->feed->settings['filter_price']['operator'];",
            $source,
            $file
        );
        $this->assertStringNotContainsString(
            "\$operator = \$this->feed->settings['filter_stock']['operator'];",
            $source,
            $file
        );
    }

    #[DataProvider('backendAdapterProvider')]
    public function testBackendAdaptersNormalizePostedOperators($file)
    {
        $source = $this->read($file);

        $this->assertStringContainsString('normalizeComparisonOperator', $source, $file);
        $this->assertStringNotContainsString(
            "'operator' => \$postSettings['filter_price']['operator'],",
            $source,
            $file
        );
        $this->assertStringNotContainsString(
            "'operator' => \$postSettings['filter_stock']['operator'],",
            $source,
            $file
        );
    }

    #[DataProvider('baseAdapterProvider')]
    public function testAllowlistIsNarrow($file)
    {
        $source = $this->read($file);

        $this->assertStringContainsString("in_array(\$operator, ['<', '>', '='], true)", $source, $file);
    }

    public function testNoOperatorReachesSqlUnnormalised()
    {
        $root = dirname(__DIR__, 2) . '/Okay/Modules/OkayCMS/Feeds';
        $offenders = [];

        foreach ($this->phpFiles($root) as $file) {
            $source = file_get_contents($file);
            if ($source === false) {
                continue;
            }

            // Кожне читання ['operator'] має бути загорнуте нормалізатором
            if (preg_match_all("/\\['operator'\\]/", $source, $m, PREG_OFFSET_CAPTURE)) {
                foreach ($m[0] as $hit) {
                    $lineStart = strrpos(substr($source, 0, $hit[1]), "\n");
                    $line = substr($source, $lineStart, $hit[1] - $lineStart + 60);

                    if (strpos($line, 'normalizeComparisonOperator') === false) {
                        $offenders[] = str_replace(dirname(__DIR__, 2) . '/', '', $file);
                        break;
                    }
                }
            }
        }

        $this->assertSame([], array_values(array_unique($offenders)));
    }

    public static function runtimeAdapterProvider()
    {
        return self::rows('Okay/Modules/OkayCMS/Feeds/Core/Presets/Adapters/', [
            'FacebookAdapter.php',
            'GoogleMerchantAdapter.php',
            'HotlineAdapter.php',
            'PriceUaAdapter.php',
            'PromUaAdapter.php',
            'RozetkaAdapter.php',
            'YmlAdapter.php',
        ]);
    }

    public static function backendAdapterProvider()
    {
        return self::rows('Okay/Modules/OkayCMS/Feeds/Backend/Core/Presets/Adapters/', [
            'BackendFacebookAdapter.php',
            'BackendGoogleMerchantAdapter.php',
            'BackendHotlineAdapter.php',
            'BackendPriceUaAdapter.php',
            'BackendPromUaAdapter.php',
            'BackendRozetkaAdapter.php',
            'BackendYmlAdapter.php',
        ]);
    }

    public static function baseAdapterProvider()
    {
        return [
            'runtime' => ['Okay/Modules/OkayCMS/Feeds/Core/Presets/AbstractPresetAdapter.php'],
            'backend' => ['Okay/Modules/OkayCMS/Feeds/Backend/Core/Presets/AbstractBackendPresetAdapter.php'],
        ];
    }

    private static function rows($dir, array $files)
    {
        $rows = [];
        foreach ($files as $file) {
            $rows[$file] = [$dir . $file];
        }

        return $rows;
    }

    private function phpFiles($dir)
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                yield $file->getPathname();
            }
        }
    }

    private function read($file)
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $file);
        $this->assertIsString($source, $file);

        return $source;
    }
}
