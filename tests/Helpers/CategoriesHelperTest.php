<?php

namespace Helpers;

use Okay\Helpers\CategoriesHelper;
use PHPUnit\Framework\TestCase;

/**
 * Guards Okay\Helpers\CategoriesHelper against the PHP 8.4+ implicit-nullable
 * parameter deprecation. Compiled in a fresh subprocess that fails fast if the
 * deprecation fires (reflection cannot tell implicit from explicit nullable).
 */
class CategoriesHelperTest extends TestCase
{
    public function testHasNoImplicitNullableDeprecation(): void
    {
        $vendorDir = dirname((new \ReflectionClass(\Composer\Autoload\ClassLoader::class))->getFileName(), 2);

        $script = 'set_error_handler(function ($no, $str) {'
            . '    if (strpos($str, "Implicitly marking") !== false) { fwrite(STDERR, $str); exit(7); }'
            . '    return false;'
            . '}, E_ALL);'
            . 'require ' . var_export($vendorDir . '/autoload.php', true) . ';'
            . 'class_exists(' . var_export(CategoriesHelper::class, true) . ');'
            . 'exit(0);';

        $cmd = escapeshellarg(PHP_BINARY) . ' -d opcache.enable_cli=0 -r ' . escapeshellarg($script) . ' 2>&1';
        exec($cmd, $output, $exitCode);

        $this->assertSame(
            0,
            $exitCode,
            CategoriesHelper::class . ' emits an implicit-nullable deprecation on PHP 8.4+:' . PHP_EOL . implode(PHP_EOL, $output)
        );
    }
}
