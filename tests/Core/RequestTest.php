<?php

namespace Core;

use Okay\Core\Request;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

/**
 * Guards Okay\Core\Request against the PHP 8.1 "Passing null to parameter of
 * type string is deprecated" for internal functions: a missing param yields
 * null internally, and preg_replace(null) would emit a deprecation — the
 * (string) cast in get()/post() must prevent it.
 *
 * The container suppresses E_DEPRECATED via error_reporting, so this test
 * installs its own E_DEPRECATED handler that throws, making any null-to-string
 * deprecation a hard failure regardless of the ambient ini settings.
 */
class RequestTest extends TestCase
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

    public function testGetStringWithMissingParam(): void
    {
        unset($_GET['php85_missing_param']);

        /** @var Request $request */
        $request = (new ReflectionClass(Request::class))->newInstanceWithoutConstructor();

        $result = $this->failOnDeprecation(
            static fn () => $request->get('php85_missing_param', 'string')
        );

        $this->assertSame('', $result);
    }
}
