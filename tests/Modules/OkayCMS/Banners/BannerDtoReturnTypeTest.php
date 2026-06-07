<?php

namespace Modules\OkayCMS\Banners;

use PHPUnit\Framework\TestCase;

/**
 * The Banners backup/settings DTOs implement JsonSerializable. PHP 8.1 emits a
 * "tentative return type" deprecation when jsonSerialize() is declared without a
 * compatible return type (and without #[\ReturnTypeWillChange]). The deprecation
 * fires at compile time, so each DTO is loaded in a fresh subprocess that fails
 * if it fires.
 */
class BannerDtoReturnTypeTest extends TestCase
{
    /**
     * @dataProvider dtoProvider
     */
    public function testHasNoTentativeReturnTypeDeprecation(string $fqcn): void
    {
        $vendorDir = dirname((new \ReflectionClass(\Composer\Autoload\ClassLoader::class))->getFileName(), 2);

        $script = 'set_error_handler(function ($no, $str) {'
            . '    if (strpos($str, "should either be compatible") !== false'
            . '        || strpos($str, "ReturnTypeWillChange") !== false) { fwrite(STDERR, $str); exit(7); }'
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
            $fqcn . ' emits a tentative-return-type deprecation on PHP 8.1+:' . PHP_EOL . implode(PHP_EOL, $output)
        );
    }

    public function dtoProvider(): array
    {
        $ns = 'Okay\Modules\OkayCMS\Banners\DTO\\';
        return [
            'BannerBackupDTO'          => [$ns . 'BannerBackupDTO'],
            'BannerImageBackupDTO'     => [$ns . 'BannerImageBackupDTO'],
            'BannerImageLangBackupDTO' => [$ns . 'BannerImageLangBackupDTO'],
            'BannerImageSettingsDTO'   => [$ns . 'BannerImageSettingsDTO'],
            'BannerSettingsDTO'        => [$ns . 'BannerSettingsDTO'],
        ];
    }
}
