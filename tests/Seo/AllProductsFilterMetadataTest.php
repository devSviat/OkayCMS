<?php

namespace Seo;

use Okay\Helpers\MetadataHelpers\AllProductsMetadataHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * /all-products з фільтром показує інші товари, ніж повний каталог, тож і
 * заголовок має бути інший. Тут перевіряється саме побудова уточнення —
 * конструктор хелпера тягне контейнер, тому екземпляр створюється без нього.
 */
class AllProductsFilterMetadataTest extends TestCase
{
    protected function setUp(): void
    {
        require_once 'Okay/Core/config/constants.php';
    }

    private function makeHelper(array $metaArray, int $metaRobots): AllProductsMetadataHelper
    {
        $reflector = new ReflectionClass(AllProductsMetadataHelper::class);

        /** @var AllProductsMetadataHelper $helper */
        $helper = $reflector->newInstanceWithoutConstructor();

        foreach (['metaArray' => $metaArray, 'metaRobots' => $metaRobots] as $name => $value) {
            $property = $reflector->getProperty($name);
            $property->setValue($helper, $value);
        }

        return $helper;
    }

    private function callPrivate(AllProductsMetadataHelper $helper, string $method, array $args = [])
    {
        return (new ReflectionClass(AllProductsMetadataHelper::class))
            ->getMethod($method)
            ->invokeArgs($helper, $args);
    }

    #[DataProvider('filterAutoMetaDataProvider')]
    public function testGetFilterAutoMeta(array $metaArray, int $metaRobots, string $expected)
    {
        $helper = $this->makeHelper($metaArray, $metaRobots);

        $this->assertSame($expected, $this->callPrivate($helper, 'getFilterAutoMeta'));
    }

    public static function filterAutoMetaDataProvider(): array
    {
        $brand = ['brand' => [7 => 'DeLonghi']];

        return [
            'без фільтрів' => [[], ROBOTS_INDEX_FOLLOW, ''],
            'один бренд' => [$brand, ROBOTS_INDEX_FOLLOW, 'DeLonghi'],
            'два бренди' => [
                ['brand' => [7 => 'DeLonghi', 9 => 'Tefal']],
                ROBOTS_INDEX_FOLLOW,
                'DeLonghi, Tefal',
            ],
            'бренд і фільтр' => [
                ['brand' => [7 => 'DeLonghi'], 'filter' => ['discounted' => 'Зі знижкою']],
                ROBOTS_INDEX_FOLLOW,
                'DeLonghi, Зі знижкою',
            ],
            'значення властивостей' => [
                ['features_values' => [3 => [11 => 'Чорний'], 5 => [21 => '1.5 л']]],
                ROBOTS_INDEX_FOLLOW,
                'Чорний, 1.5 л',
            ],
            'бренд і властивість' => [
                $brand + ['features_values' => [3 => [11 => 'Чорний']]],
                ROBOTS_INDEX_FOLLOW,
                'DeLonghi, Чорний',
            ],
            // page і sort змісту сторінки не змінюють: номер сторінки title
            // отримує окремо, а порядок сортування взагалі не про товари.
            'page і sort не потрапляють у заголовок' => [
                $brand + ['page' => '2', 'sort' => 'price'],
                ROBOTS_INDEX_FOLLOW,
                'DeLonghi',
            ],
            'noindex,follow лишається без уточнення' => [$brand, ROBOTS_NOINDEX_FOLLOW, ''],
            'noindex,nofollow лишається без уточнення' => [$brand, ROBOTS_NOINDEX_NOFOLLOW, ''],
        ];
    }

    #[DataProvider('withFilterAutoMetaDataProvider')]
    public function testWithFilterAutoMeta(array $metaArray, string $template, string $expected)
    {
        $helper = $this->makeHelper($metaArray, ROBOTS_INDEX_FOLLOW);

        $this->assertSame($expected, $this->callPrivate($helper, 'withFilterAutoMeta', [$template]));
    }

    public static function withFilterAutoMetaDataProvider(): array
    {
        return [
            'без фільтра шаблон не змінюється' => [[], 'Каталог усіх запчастин', 'Каталог усіх запчастин'],
            'бренд додається через пробіл' => [
                ['brand' => [7 => 'DeLonghi']],
                'Каталог усіх запчастин',
                'Каталог усіх запчастин DeLonghi',
            ],
            'порожній шаблон не лишає пробілу' => [
                ['brand' => [7 => 'DeLonghi']],
                '',
                'DeLonghi',
            ],
        ];
    }

    /**
     * Опис сторінки ховається за наявністю фільтра, а не за наявністю уточнення:
     * на закритій від індексації комбінації уточнення порожнє, але текст усе одно
     * описував би не той набір товарів.
     */
    #[DataProvider('hasSelectedFiltersDataProvider')]
    public function testHasSelectedFilters(array $metaArray, int $metaRobots, bool $expected)
    {
        $helper = $this->makeHelper($metaArray, $metaRobots);

        $this->assertSame($expected, $this->callPrivate($helper, 'hasSelectedFilters'));
    }

    public static function hasSelectedFiltersDataProvider(): array
    {
        return [
            'порожньо' => [[], ROBOTS_INDEX_FOLLOW, false],
            'лише сторінка' => [['page' => '2'], ROBOTS_INDEX_FOLLOW, false],
            'лише сортування' => [['sort' => 'price'], ROBOTS_INDEX_FOLLOW, false],
            'бренд' => [['brand' => [7 => 'DeLonghi']], ROBOTS_INDEX_FOLLOW, true],
            'фільтр' => [['filter' => ['discounted' => 'Зі знижкою']], ROBOTS_INDEX_FOLLOW, true],
            'властивість' => [['features_values' => [3 => [11 => 'Чорний']]], ROBOTS_INDEX_FOLLOW, true],
            'фільтр є навіть коли сторінка закрита від індексації' => [
                ['brand' => [7 => 'DeLonghi'], 'filter' => ['discounted' => 'Зі знижкою']],
                ROBOTS_NOINDEX_NOFOLLOW,
                true,
            ],
        ];
    }

    /**
     * Уточнення будується з даних, які збирає контролер. Якщо він перестане їх
     * передавати, тести вище лишаться зеленими, а сторінки — однаковими.
     */
    public function testControllerPassesFilterDataToMetadataHelper()
    {
        $source = file_get_contents('Okay/Controllers/ProductsController.php');

        $this->assertMatchesRegularExpression(
            '/\$allProductsMetadataHelper->setUp\(\s*[^;]*\$metaArray,\s*\$catalogRobots\s*\);/',
            $source,
            'ProductsController має передавати $metaArray і robots у AllProductsMetadataHelper::setUp()'
        );
    }
}
