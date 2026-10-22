<?php

namespace Security;

use Okay\Core\Cart;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Cookie shopping_cart приходить від клієнта і без перевірки ставала кошиком:
 * від'ємна кількість зменшувала total_price замовлення, а при закритті
 * замовлення ще й додавала товар на склад.
 */
class CartCookieItemsTest extends TestCase
{
    #[DataProvider('itemsProvider')]
    public function testOnlyPositiveIntegerAmountsForPositiveIntegerVariantsSurvive($items, $expected)
    {
        $this->assertSame($expected, Cart::sanitizeItems($items));
    }

    public static function itemsProvider()
    {
        return [
            'valid'               => [['255' => 3, '136' => 1], [255 => 3, 136 => 1]],
            'numeric string'      => [['255' => '3'], [255 => 3]],
            'negative amount'     => [['255' => 3, '136' => -2], [255 => 3]],
            'zero amount'         => [['255' => 0], []],
            'fraction'            => [['255' => 2.5], []],
            'fraction string'     => [['255' => '2.5'], []],
            'exponent string'     => [['255' => '1e3'], []],
            'array amount'        => [['255' => [1]], []],
            'null amount'         => [['255' => null], []],
            'bool amount'         => [['255' => true], []],
            'non-numeric variant' => [['abc' => 1, '255' => 1], [255 => 1]],
            'zero variant'        => [['0' => 1], []],
            'negative variant'    => [['-5' => 1], []],
            'list instead of map' => [[3, 4], [1 => 4]],
        ];
    }

    #[DataProvider('clampProvider')]
    public function testAmountIsClampedLikeAddItem($amount, $stock, $maxOrderAmount, $expected)
    {
        $this->assertSame($expected, Cart::clampAmount($amount, $stock, $maxOrderAmount));
    }

    public static function clampProvider()
    {
        return [
            'within stock'           => [3, 10, 50, 3],
            'above stock'            => [1000, 10, 50, 10],
            // Нескінченний склад VariantsEntity віддає як stock = max_order_amount.
            'infinite stock'         => [1000, 50, 50, 50],
            'preorder without stock' => [1000, 0, 50, 50],
            'below one'              => [0, 10, 50, 1],
        ];
    }
}
