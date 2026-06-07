<?php

namespace Core\Classes;

use Okay\Core\Classes\Discount;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * DiscountsHelper::buildFromDB() assigns $id and $position on a Discount; they
 * used to be undeclared, triggering the PHP 8.2 dynamic property deprecation
 * (which broke headers on OrderAdmin). Both must be declared properties.
 */
class DiscountTest extends TestCase
{
    /**
     * @dataProvider declaredPropertyProvider
     */
    public function testDeclaresProperty(string $property): void
    {
        $this->assertTrue(
            (new ReflectionClass(Discount::class))->hasProperty($property),
            'Discount::$' . $property . ' must be a declared property (was assigned dynamically).'
        );
    }

    public function declaredPropertyProvider(): array
    {
        return [
            'id'       => ['id'],
            'position' => ['position'],
        ];
    }
}
