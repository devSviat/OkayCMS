<?php

namespace Core;

use Okay\Core\Image;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

/**
 * Image::addResizeParams() runs pathinfo() on its argument. When a template
 * calls {resize} with a null/empty image, PHP 8.1 emits "Passing null to
 * parameter of type string is deprecated" — which, on the binary resize
 * endpoint, corrupts the response. The (string) cast must prevent it.
 */
class ImageTest extends TestCase
{
    public function testAddResizeParamsAcceptsNull(): void
    {
        /** @var Image $image */
        $image = (new ReflectionClass(Image::class))->newInstanceWithoutConstructor();

        set_error_handler(
            static function ($no, $str): bool {
                throw new RuntimeException($str);
            },
            E_DEPRECATED
        );

        try {
            $result = $image->addResizeParams(null, 100, 100);
        } finally {
            restore_error_handler();
        }

        $this->assertIsString($result);
    }
}
