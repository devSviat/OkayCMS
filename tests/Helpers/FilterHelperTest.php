<?php

namespace Helpers;

use Okay\Helpers\FilterHelper;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

/**
 * Two PHP 8.x guards for Okay\Helpers\FilterHelper:
 *  - parseFilterUrl(null) must not emit the 8.1 "Passing null to string"
 *    deprecation (explode/strip_tags receive a (string) cast);
 *  - the class must not declare implicitly-nullable params (8.4+), which is
 *    verified by compiling it in a fresh subprocess.
 */
class FilterHelperTest extends TestCase
{
    /**
     * @return mixed
     */
    private function failOnDeprecation(callable $fn)
    {
        set_error_handler(
            static function ($no, $str): bool {
                throw new RuntimeException($str);
            },
            E_DEPRECATED
        );

        try {
            return $fn();
        } finally {
            restore_error_handler();
        }
    }

    public function testParseFilterUrlAcceptsNull(): void
    {
        /** @var FilterHelper $helper */
        $helper = (new ReflectionClass(FilterHelper::class))->newInstanceWithoutConstructor();

        $result = $this->failOnDeprecation(static fn () => $helper->parseFilterUrl(null));

        $this->assertSame([''], $result);
    }

    public function testHasNoImplicitNullableDeprecation(): void
    {
        $vendorDir = dirname((new \ReflectionClass(\Composer\Autoload\ClassLoader::class))->getFileName(), 2);

        $script = 'set_error_handler(function ($no, $str) {'
            . '    if (strpos($str, "Implicitly marking") !== false) { fwrite(STDERR, $str); exit(7); }'
            . '    return false;'
            . '}, E_ALL);'
            . 'require ' . var_export($vendorDir . '/autoload.php', true) . ';'
            . 'class_exists(' . var_export(FilterHelper::class, true) . ');'
            . 'exit(0);';

        $cmd = escapeshellarg(PHP_BINARY) . ' -d opcache.enable_cli=0 -r ' . escapeshellarg($script) . ' 2>&1';
        exec($cmd, $output, $exitCode);

        $this->assertSame(
            0,
            $exitCode,
            FilterHelper::class . ' emits an implicit-nullable deprecation on PHP 8.4+:' . PHP_EOL . implode(PHP_EOL, $output)
        );
    }
}
