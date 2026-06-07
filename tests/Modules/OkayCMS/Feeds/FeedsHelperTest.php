<?php

namespace Modules\OkayCMS\Feeds;

use Okay\Modules\OkayCMS\Feeds\Helpers\FeedsHelper;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * $languages, $firstLanguage and $language used to be assigned in the
 * constructor without property declarations, triggering the PHP 8.2 dynamic
 * property deprecation; they must now be declared.
 */
class FeedsHelperTest extends TestCase
{
    /**
     * @dataProvider declaredPropertyProvider
     */
    public function testDeclaresProperty(string $property): void
    {
        $this->assertTrue(
            (new ReflectionClass(FeedsHelper::class))->hasProperty($property),
            'FeedsHelper::$' . $property . ' must be a declared property (was assigned dynamically).'
        );
    }

    public function declaredPropertyProvider(): array
    {
        return [
            'languages'     => ['languages'],
            'firstLanguage' => ['firstLanguage'],
            'language'      => ['language'],
        ];
    }
}
