<?php

namespace Admin\Controllers;

use PHPUnit\Framework\TestCase;

/**
 * SettingsGeneralAdmin used `case 'ua';` (semicolon) in its switch, which PHP
 * 8.5 deprecates ("Case statements followed by a semicolon are deprecated").
 * The deprecation fires at compile time, so the class is loaded in a fresh
 * subprocess that fails if it fires.
 */
class SettingsGeneralAdminTest extends TestCase
{
    public function testHasNoCaseSemicolonDeprecation(): void
    {
        $vendorDir = dirname((new \ReflectionClass(\Composer\Autoload\ClassLoader::class))->getFileName(), 2);
        $fqcn = 'Okay\Admin\Controllers\SettingsGeneralAdmin';

        $script = 'set_error_handler(function ($no, $str) {'
            . '    if (strpos($str, "Case statements followed by a semicolon") !== false) { fwrite(STDERR, $str); exit(7); }'
            . '    return false;'
            . '}, E_ALL);'
            . 'require ' . var_export($vendorDir . '/autoload.php', true) . ';'
            . 'class_exists(' . var_export($fqcn, true) . ');'
            . 'exit(0);';

        $cmd = escapeshellarg(PHP_BINARY) . ' -d opcache.enable_cli=0 -r ' . escapeshellarg($script) . ' 2>&1';
        exec($cmd, $output, $exitCode);

        $this->assertSame(
            0,
            $exitCode,
            $fqcn . ' emits a case-semicolon deprecation on PHP 8.5:' . PHP_EOL . implode(PHP_EOL, $output)
        );
    }
}
