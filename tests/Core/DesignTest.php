<?php

namespace Core;

use Okay\Core\Design;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

/**
 * Guards Okay\Core\Design::getModuleTemplatesDir() against the PHP 8.1
 * "Passing null to parameter of type string is deprecated" — the directory may
 * be unset, and downstream string functions must receive a string, not null.
 */
class DesignTest extends TestCase
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

    public function testGetModuleTemplatesDirAcceptsNull(): void
    {
        /** @var Design $design */
        $design = (new ReflectionClass(Design::class))->newInstanceWithoutConstructor();

        $result = $this->failOnDeprecation(static fn () => $design->getModuleTemplatesDir());

        $this->assertSame('', $result);
    }
}
