<?php

namespace Helpers;

use Okay\Core\EntityFactory;
use Okay\Entities\DiscountsEntity;
use Okay\Entities\ProductsEntity;
use Okay\Entities\PurchasesEntity;
use Okay\Entities\VariantsEntity;
use Okay\Helpers\DiscountsHelper;
use Okay\Helpers\MoneyHelper;
use Okay\Helpers\OrdersHelper;
use Okay\Helpers\ProductsHelper;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

/**
 * Сторінка замовлення покупця показує рядки й після видалення товару з каталогу:
 * назва, артикул і ціна лежать у самій покупці. Її product_id при цьому порожній,
 * а null як ключ масиву в PHP 8.5 - депрекейт навіть усередині empty().
 */
class OrdersHelperTest extends TestCase
{
    public function testDeletedProductLeavesPurchaseWithoutProduct(): void
    {
        $purchase = (object)['id' => 45, 'product_id' => null, 'variant_id' => null, 'undiscounted_price' => 0];

        $purchases = $this->helper($purchase)->getOrderPurchasesList(5);

        $this->assertSame([45 => $purchase], $purchases);
        $this->assertObjectNotHasProperty('product', $purchase);
        $this->assertObjectNotHasProperty('variant', $purchase);
    }

    public function testLivingProductStillLandsOnItsPurchase(): void
    {
        $purchase = (object)['id' => 46, 'product_id' => 7, 'variant_id' => 3, 'undiscounted_price' => 0];
        $product  = (object)['id' => 7];
        $variant  = (object)['id' => 3, 'product_id' => 7];

        $purchases = $this->helper($purchase, [7 => $product], [3 => $variant])->getOrderPurchasesList(5);

        $this->assertSame($product, $purchases[46]->product);
        $this->assertSame($variant, $purchases[46]->variant);
    }

    /**
     * Конструктор помічника тягне пів ядра, тож збираємо обʼєкт без нього і
     * підставляємо лише те, що читає getOrderPurchasesList().
     */
    private function helper(object $purchase, array $products = [], array $variants = []): OrdersHelper
    {
        // mappedBy() у Entity фінальний - лишаємо справжній, він віддає той самий обʼєкт.
        $purchasesEntity = $this->createStub(PurchasesEntity::class);
        $purchasesEntity->method('find')->willReturn([$purchase->id => $purchase]);

        $productsEntity = $this->createStub(ProductsEntity::class);
        $productsEntity->method('find')->willReturn($products);

        $variantsEntity = $this->createStub(VariantsEntity::class);
        $variantsEntity->method('find')->willReturn($variants);

        $entityFactory = $this->createStub(EntityFactory::class);
        $entityFactory->method('get')->willReturnCallback(fn ($class) => match ($class) {
            PurchasesEntity::class => $purchasesEntity,
            ProductsEntity::class  => $productsEntity,
            VariantsEntity::class  => $variantsEntity,
        });

        $productsHelper = $this->createStub(ProductsHelper::class);
        $productsHelper->method('attachVariants')->willReturn($products);
        $productsHelper->method('attachMainImages')->willReturn($products);

        $moneyHelper = $this->createStub(MoneyHelper::class);
        $moneyHelper->method('convertVariantsPriceToMainCurrency')->willReturn($variants);

        $discountsEntity = $this->createStub(DiscountsEntity::class);
        $discountsEntity->method('find')->willReturn([]);

        $properties = [
            'entityFactory'   => $entityFactory,
            'productsHelper'  => $productsHelper,
            'moneyHelper'     => $moneyHelper,
            'discountsEntity' => $discountsEntity,
            'discountsHelper' => $this->createStub(DiscountsHelper::class),
        ];

        $helper = (new ReflectionClass(OrdersHelper::class))->newInstanceWithoutConstructor();
        foreach ($properties as $name => $value) {
            (new ReflectionProperty(OrdersHelper::class, $name))->setValue($helper, $value);
        }

        return $helper;
    }
}
