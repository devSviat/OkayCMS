<?php

namespace Core;

use Okay\Core\BackendTranslations;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * BackendTranslations is filled from the DB / language files via dynamic
 * properties, which PHP 8.2 deprecates unless the class declares
 * #[\AllowDynamicProperties].
 */
class BackendTranslationsTest extends TestCase
{
    public function testAllowsDynamicProperties(): void
    {
        $attributes = (new ReflectionClass(BackendTranslations::class))->getAttributes(\AllowDynamicProperties::class);

        $this->assertNotEmpty(
            $attributes,
            BackendTranslations::class . ' assigns dynamic properties and must carry #[\\AllowDynamicProperties] for PHP 8.2+.'
        );
    }
}
