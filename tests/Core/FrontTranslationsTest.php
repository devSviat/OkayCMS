<?php

namespace Core;

use Okay\Core\FrontTranslations;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * FrontTranslations is filled from the DB / language files via dynamic
 * properties, which PHP 8.2 deprecates unless the class declares
 * #[\AllowDynamicProperties].
 */
class FrontTranslationsTest extends TestCase
{
    public function testAllowsDynamicProperties(): void
    {
        $attributes = (new ReflectionClass(FrontTranslations::class))->getAttributes(\AllowDynamicProperties::class);

        $this->assertNotEmpty(
            $attributes,
            FrontTranslations::class . ' assigns dynamic properties and must carry #[\\AllowDynamicProperties] for PHP 8.2+.'
        );
    }
}
