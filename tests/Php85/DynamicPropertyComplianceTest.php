<?php

namespace Php85;

use Okay\Core\FrontTranslations;
use Okay\Core\BackendTranslations;
use Okay\Entities\TranslationsEntity;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Guards against the PHP 8.2 "Creation of dynamic property" deprecation.
 *
 * Classes that intentionally populate properties dynamically (the translation
 * containers, filled from the DB / language files) must declare the
 * #[\AllowDynamicProperties] attribute. Classes that used a single ad-hoc
 * dynamic property must declare it explicitly instead.
 */
class DynamicPropertyComplianceTest extends TestCase
{
    public function allowDynamicPropertiesProvider(): array
    {
        return [
            FrontTranslations::class   => [FrontTranslations::class],
            BackendTranslations::class => [BackendTranslations::class],
        ];
    }

    /**
     * @dataProvider allowDynamicPropertiesProvider
     */
    public function testTranslationContainerAllowsDynamicProperties(string $fqcn): void
    {
        $attributes = (new ReflectionClass($fqcn))->getAttributes(\AllowDynamicProperties::class);

        $this->assertNotEmpty(
            $attributes,
            $fqcn . ' assigns dynamic properties and must carry #[\\AllowDynamicProperties] for PHP 8.2+.'
        );
    }

    public function testTranslationsEntityDeclaresTemplateOnly(): void
    {
        $this->assertTrue(
            (new ReflectionClass(TranslationsEntity::class))->hasProperty('templateOnly'),
            'TranslationsEntity::$templateOnly must be a declared property (was a dynamic property).'
        );
    }
}
