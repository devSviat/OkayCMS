<?php

namespace Core;

use PHPUnit\Framework\TestCase;

/**
 * Guards against the PHP 8.4+ "Implicitly marking parameter as nullable"
 * deprecation in Okay\Core\Money (e.g. `int $x = null` instead of `?int $x = null`).
 *
 * The deprecation is emitted at compile time and reflection cannot distinguish
 * implicit from explicit nullable types, so the class is compiled in a fresh
 * subprocess that fails fast if the deprecation fires.
 */
class MoneyTest extends TestCase
{
    public function testHasNoImplicitNullableDeprecation(): void
    {
        $vendorDir = dirname((new \ReflectionClass(\Composer\Autoload\ClassLoader::class))->getFileName(), 2);

        $script = 'set_error_handler(function ($no, $str) {'
            . '    if (strpos($str, "Implicitly marking") !== false) { fwrite(STDERR, $str); exit(7); }'
            . '    return false;'
            . '}, E_ALL);'
            . 'require ' . var_export($vendorDir . '/autoload.php', true) . ';'
            . 'class_exists(' . var_export(\Okay\Core\Money::class, true) . ');'
            . 'exit(0);';

        $cmd = escapeshellarg(PHP_BINARY) . ' -d opcache.enable_cli=0 -r ' . escapeshellarg($script) . ' 2>&1';
        exec($cmd, $output, $exitCode);

        $this->assertSame(
            0,
            $exitCode,
            'Okay\Core\Money emits an implicit-nullable deprecation on PHP 8.4+:' . PHP_EOL . implode(PHP_EOL, $output)
        );
    }
}
