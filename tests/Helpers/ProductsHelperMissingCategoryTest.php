<?php

namespace Helpers;

use Okay\Entities\BrandsEntity;
use Okay\Entities\CategoriesEntity;
use Okay\Helpers\MetadataHelpers\ProductMetadataHelper;
use Okay\Helpers\ProductsHelper;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Товар без головної категорії валив усю сторінку категорії: findOne() віддає
 * false, а setUp() приймає лише ?object. На проді це HTTP 500 з порожнім тілом
 * для всього розділу через один зіпсований рядок у базі.
 */
class ProductsHelperMissingCategoryTest extends TestCase
{
    public function testProductWithoutMainCategoryDoesNotBreakTheListing(): void
    {
        $products = [$this->product(['main_category_id' => null, 'brand_id' => null])];

        $result = $this->helper()->attachDescriptionByTemplate($products);

        $this->assertCount(1, $result);
        $this->assertSame($products[0], $result[0]);
    }

    public function testProductPointingAtADeletedCategoryDoesNotBreakTheListing(): void
    {
        $products = [$this->product(['main_category_id' => 999999, 'brand_id' => null])];

        $result = $this->helper()->attachDescriptionByTemplate($products);

        $this->assertCount(1, $result);
    }

    /**
     * Одного зіпсованого товару досить, щоб покласти сторінку, тож перевіряється
     * саме суміш: здоровий товар поруч має пройти як і раніше.
     */
    public function testHealthyProductInTheSameListIsStillProcessed(): void
    {
        $category = (object)['id' => 7, 'name' => 'Водонагрівачі', 'name_h1' => '', 'path' => []];

        $helper = $this->helper($category);
        $products = [
            $this->product(['main_category_id' => null, 'brand_id' => null]),
            $this->product(['main_category_id' => 7, 'brand_id' => null]),
        ];

        $result = $helper->attachDescriptionByTemplate($products);

        $this->assertCount(2, $result);
    }

    public function testNullBrandIdIsNotUsedAsAnArrayOffset(): void
    {
        $seen = [];
        set_error_handler(function ($no, $str) use (&$seen) {
            $seen[] = $str;
            return true;
        }, E_ALL);

        try {
            $this->helper()->attachDescriptionByTemplate([
                $this->product(['main_category_id' => null, 'brand_id' => null]),
            ]);
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $seen, 'сторінка категорії пише в лог: ' . implode('; ', $seen));
    }

    /**
     * Без annotation і description: за ними йде getAnnotation(), якому потрібні
     * власні залежності помічника, а перевіряється тут саме те, що обривало
     * сторінку - тип другого аргументу setUp().
     */
    private function product(array $fields): object
    {
        return (object)($fields + ['id' => 1, 'name' => 'Товар']);
    }

    /**
     * Помічник ходить у базу за категоріями й брендами, а перевіряється тут
     * поведінка на порожньому результаті - тож сутності підмінені.
     */
    private function helper(?object $category = null): ProductsHelper
    {
        $categories = new class ($category) {
            private $category;
            public function __construct($category)
            {
                $this->category = $category;
            }
            public function findOne(array $filter = [])
            {
                if ($this->category !== null && ($filter['id'] ?? null) === $this->category->id) {
                    return $this->category;
                }

                return false; // так поводиться Entity::findOne(), коли нічого не знайшов
            }
        };

        $brands = new class {
            public function find(array $filter = [])
            {
                return [];
            }
        };

        $entityFactory = new class ($categories, $brands) {
            private $categories;
            private $brands;
            public function __construct($categories, $brands)
            {
                $this->categories = $categories;
                $this->brands = $brands;
            }
            public function get($class)
            {
                return $class === BrandsEntity::class ? $this->brands : $this->categories;
            }
        };

        $reflection = new ReflectionClass(ProductsHelper::class);
        $helper = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('entityFactory')->setValue($helper, $entityFactory);
        $reflection->getProperty('productMetadataHelper')->setValue(
            $helper,
            (new ReflectionClass(ProductMetadataHelper::class))->newInstanceWithoutConstructor()
        );

        return $helper;
    }
}
