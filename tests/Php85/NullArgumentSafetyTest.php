<?php

namespace Php85;

use Okay\Helpers\FilterHelper;
use Okay\Core\Design;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

/**
 * Guards against the PHP 8.1 "Passing null to parameter of type string is
 * deprecated" for internal functions.
 *
 * The container suppresses E_DEPRECATED via error_reporting, so this test
 * installs its own E_DEPRECATED handler that throws, making any null-to-string
 * deprecation a hard failure regardless of the ambient ini settings.
 *
 * Only call sites that can be exercised without the full DI graph are covered
 * here (instances are built with newInstanceWithoutConstructor); the remaining
 * data-flow sites are verified by the runtime debug crawl documented in the
 * audit notes.
 */
class NullArgumentSafetyTest extends TestCase
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

    public function testGetModuleTemplatesDirAcceptsNull(): void
    {
        /** @var Design $design */
        $design = (new ReflectionClass(Design::class))->newInstanceWithoutConstructor();

        $result = $this->failOnDeprecation(static fn () => $design->getModuleTemplatesDir());

        $this->assertSame('', $result);
    }
}
