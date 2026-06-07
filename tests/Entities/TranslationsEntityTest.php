<?php

namespace Entities;

use Okay\Entities\TranslationsEntity;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * $templateOnly used to be assigned as a dynamic property, which PHP 8.2
 * deprecates; it must now be a declared property.
 */
class TranslationsEntityTest extends TestCase
{
    public function testDeclaresTemplateOnly(): void
    {
        $this->assertTrue(
            (new ReflectionClass(TranslationsEntity::class))->hasProperty('templateOnly'),
            'TranslationsEntity::$templateOnly must be a declared property (was a dynamic property).'
        );
    }
}
