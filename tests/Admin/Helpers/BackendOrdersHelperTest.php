<?php

namespace Admin\Helpers;

use Okay\Admin\Helpers\BackendOrdersHelper;
use Okay\Entities\DiscountsEntity;
use Okay\Entities\ImagesEntity;
use Okay\Entities\ProductsEntity;
use Okay\Entities\PurchasesEntity;
use Okay\Entities\VariantsEntity;
use Okay\Helpers\DiscountsHelper;
use Okay\Helpers\MoneyHelper;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

/**
 * Видалення товару лишає рядок замовлення на місці й обнуляє його product_id
 * (ProductsEntity::forgetProductReferencesByProductsIds). З PHP 8.5 null як ключ
 * масиву - депрекейт, причому isset() і empty() його теж виводять, тож охорона
 * навколо пошуку товару сама й була джерелом повідомлення.
 */
class BackendOrdersHelperTest extends TestCase
{
    public function testDeletedProductLeavesPurchaseWithoutProduct(): void
    {
        $purchase = (object)['id' => 45, 'product_id' => null, 'variant_id' => null, 'undiscounted_price' => 0];

        $purchases = $this->helper($purchase)->findOrderPurchases((object)['id' => 5]);

        $this->assertSame([45 => $purchase], $purchases);
        $this->assertObjectNotHasProperty('product', $purchase);
        $this->assertObjectNotHasProperty('variant', $purchase);
    }

    public function testLivingProductStillLandsOnItsPurchase(): void
    {
        $purchase = (object)['id' => 46, 'product_id' => 7, 'variant_id' => 3, 'undiscounted_price' => 0];
        $product  = (object)['id' => 7, 'main_image_id' => null];
        $variant  = (object)['id' => 3, 'product_id' => 7];

        $purchases = $this->helper($purchase, [$product], [3 => $variant])->findOrderPurchases((object)['id' => 5]);

        $this->assertSame($product, $purchases[46]->product);
        $this->assertSame($variant, $purchases[46]->variant);
    }

    /**
     * Конструктор помічника тягне пів ядра, тож збираємо обʼєкт без нього і
     * підставляємо лише те, що читає findOrderPurchases().
     */
    private function helper(object $purchase, array $products = [], array $variants = []): BackendOrdersHelper
    {
        // mappedBy() у Entity фінальний - лишаємо справжній, він віддає той самий обʼєкт.
        $purchasesEntity = $this->createStub(PurchasesEntity::class);
        $purchasesEntity->method('find')->willReturn([$purchase->id => $purchase]);

        $productsEntity = $this->createStub(ProductsEntity::class);
        $productsEntity->method('find')->willReturn($products);

        $variantsEntity = $this->createStub(VariantsEntity::class);
        $variantsEntity->method('find')->willReturn($variants);

        $moneyHelper = $this->createStub(MoneyHelper::class);
        $moneyHelper->method('convertVariantsPriceToMainCurrency')->willReturn($variants);

        $imagesEntity = $this->createStub(ImagesEntity::class);
        $imagesEntity->method('find')->willReturn([]);

        $discountsEntity = $this->createStub(DiscountsEntity::class);
        $discountsEntity->method('find')->willReturn([]);

        $properties = [
            'purchasesEntity' => $purchasesEntity,
            'productsEntity'  => $productsEntity,
            'imagesEntity'    => $imagesEntity,
            'variantsEntity'  => $variantsEntity,
            'moneyHelper'     => $moneyHelper,
            'discountsEntity' => $discountsEntity,
            'discountsHelper' => $this->createStub(DiscountsHelper::class),
        ];

        $helper = (new ReflectionClass(BackendOrdersHelper::class))->newInstanceWithoutConstructor();
        foreach ($properties as $name => $value) {
            (new ReflectionProperty(BackendOrdersHelper::class, $name))->setValue($helper, $value);
        }

        return $helper;
    }
}
