<?php

namespace Php85;

use PHPUnit\Framework\TestCase;

/**
 * Guards against the PHP 8.4+ "Implicitly marking parameter as nullable"
 * deprecation (e.g. `string $x = null` instead of `?string $x = null`).
 *
 * The deprecation is emitted at compile time, and PHP reflection cannot
 * distinguish implicit from explicit nullable types (both report `?string`).
 * So each class is compiled in a fresh subprocess with an error handler that
 * fails fast if the deprecation fires while the class file is loaded.
 */
class ImplicitNullableComplianceTest extends TestCase
{
    public function nullableClassProvider(): array
    {
        return [
            'Okay\Core\Money'                => ['Okay\Core\Money'],
            'Okay\Core\Response'             => ['Okay\Core\Response'],
            'Okay\Helpers\FilterHelper'      => ['Okay\Helpers\FilterHelper'],
            'Okay\Helpers\CatalogHelper'     => ['Okay\Helpers\CatalogHelper'],
            'Okay\Helpers\CategoriesHelper'  => ['Okay\Helpers\CategoriesHelper'],
        ];
    }

    /**
     * @dataProvider nullableClassProvider
     */
    public function testClassHasNoImplicitNullableDeprecation(string $fqcn): void
    {
        $root = dirname(__DIR__, 2);
        $autoload = $root . '/vendor/autoload.php';

        $script = 'error_reporting(E_ALL);'
            . 'set_error_handler(function ($no, $str) {'
            . '    if (strpos($str, "Implicitly marking") !== false) {'
            . '        fwrite(STDERR, $str);'
            . '        exit(7);'
            . '    }'
            . '    return false;'
            . '});'
            . 'require ' . var_export($autoload, true) . ';'
            . 'class_exists(' . var_export($fqcn, true) . ');'
            . 'exit(0);';

        $cmd = escapeshellarg(PHP_BINARY)
            . ' -d opcache.enable_cli=0'
            . ' -r ' . escapeshellarg($script)
            . ' 2>&1';

        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        $this->assertSame(
            0,
            $exitCode,
            $fqcn . ' emits an implicit-nullable deprecation on PHP 8.4+:' . PHP_EOL . implode(PHP_EOL, $output)
        );
    }
}
