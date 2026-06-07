<?php

namespace Php85;

use PHPUnit\Framework\TestCase;

/**
 * Guards against the PHP 8.1+ "tentative return type" deprecation emitted when
 * a class implements an internal interface method (e.g. JsonSerializable::
 * jsonSerialize(): mixed) without declaring a compatible return type and
 * without the #[\ReturnTypeWillChange] attribute.
 *
 * Like implicit-nullable, the deprecation is emitted at compile time, so each
 * class is loaded in a fresh subprocess that fails if it fires.
 */
class TentativeReturnTypeComplianceTest extends TestCase
{
    public function tentativeReturnTypeClassProvider(): array
    {
        $ns = 'Okay\Modules\OkayCMS\Banners\DTO\\';
        return [
            $ns . 'BannerBackupDTO'         => [$ns . 'BannerBackupDTO'],
            $ns . 'BannerImageBackupDTO'    => [$ns . 'BannerImageBackupDTO'],
            $ns . 'BannerImageLangBackupDTO' => [$ns . 'BannerImageLangBackupDTO'],
            $ns . 'BannerImageSettingsDTO'  => [$ns . 'BannerImageSettingsDTO'],
            $ns . 'BannerSettingsDTO'       => [$ns . 'BannerSettingsDTO'],
        ];
    }

    /**
     * @dataProvider tentativeReturnTypeClassProvider
     */
    public function testClassHasNoTentativeReturnTypeDeprecation(string $fqcn): void
    {
        $root = dirname(__DIR__, 2);
        $autoload = $root . '/vendor/autoload.php';

        $script = 'error_reporting(E_ALL);'
            . 'set_error_handler(function ($no, $str) {'
            . '    if (strpos($str, "should either be compatible") !== false'
            . '        || strpos($str, "ReturnTypeWillChange") !== false) {'
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
            $fqcn . ' emits a tentative-return-type deprecation on PHP 8.1+:' . PHP_EOL . implode(PHP_EOL, $output)
        );
    }
}
