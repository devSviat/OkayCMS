<?php

namespace Core\Routes;

use Okay\Core\Routes\AbstractRoute;
use Okay\Core\Routes\BlogCategoryRoute;
use Okay\Core\Routes\CategoryRoute;
use Okay\Core\Routes\PostRoute;
use Okay\Core\Routes\ProductRoute;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use stdClass;

/**
 * У ланцюжку генерації URL єдине чутливе до регістру порівняння — ключ масиву
 * $routeAliases. Прогрів кешу кладе туди рядки з ok_router_cache як є, а
 * унікальний індекс url_type живе в utf8mb4_general_ci і регістр ігнорує. Через
 * це рядок `product-0B200-03580600` не влучав у пошук за `product-0b200-03580600`:
 * код вважав, що кешу нема, робив INSERT і отримував 1062 Duplicate entry — по
 * одному на кожен такий рядок, і так на кожному запиті /sitemap.xml, нескінченно.
 *
 * Ключ має нормалізуватись однаково на записі й на читанні.
 */
class AbstractRouteAliasCaseTest extends TestCase
{
    /**
     * Нащадки перевизначають `protected static $routeAliases`, тож скидаємо
     * кожну оголошену властивість, а не лише батьківську: інакше тести
     * протікають один в одного, а сам тест переживе можливий перехід
     * self:: → static:: у майбутньому.
     */
    private const ROUTE_CLASSES = [
        AbstractRoute::class,
        ProductRoute::class,
        CategoryRoute::class,
        PostRoute::class,
        BlogCategoryRoute::class,
    ];

    protected function setUp(): void
    {
        foreach (self::ROUTE_CLASSES as $class) {
            (new ReflectionProperty($class, 'routeAliases'))->setValue(null, []);
        }
    }

    protected function tearDown(): void
    {
        $this->setUp();
    }

    public function testAliasStoredInUpperCaseIsFoundByLowerCaseLookup(): void
    {
        ProductRoute::setUrlSlugAlias('product-0B200-03580600', 'gadgets/product-0B200-03580600');

        $this->assertSame(
            'gadgets/product-0B200-03580600',
            ProductRoute::getUrlSlugAlias('product-0b200-03580600')
        );
    }

    public function testAliasStoredInLowerCaseIsFoundByUpperCaseLookup(): void
    {
        ProductRoute::setUrlSlugAlias('product-0b200-03580600', 'gadgets/product-0b200-03580600');

        $this->assertSame(
            'gadgets/product-0b200-03580600',
            ProductRoute::getUrlSlugAlias('product-0B200-03580600')
        );
    }

    /**
     * mergeUrlSlugAlias() — це шлях, яким рядки з бази потрапляють у масив під
     * час прогріву кешу. Нормалізовані рядки беремо як є.
     */
    public function testMergeLoadsNormalisedRows(): void
    {
        ProductRoute::mergeUrlSlugAlias([
            $this->cacheRow('item-as00004434', 'goods/item-as00004434'),
        ]);

        $this->assertSame(
            'goods/item-as00004434',
            ProductRoute::getUrlSlugAlias('item-as00004434')
        );
    }

    /**
     * А от рядок, url якого сам не в нижньому регістрі, — застарілий: його
     * писав ще код без нормалізації. Якби ми його підхопили, пошук почав би в
     * нього влучати й повертати збережений там slug, тобто сторінки й фіди
     * назавжди лишились би зі старим урлом, а RouterCacheEntity::add() до
     * такого рядка вже не дійшов би й не полагодив би його.
     *
     * Тому такі рядки пропускаємо: пошук промахується, стратегія генерує slug
     * заново з джерела, і upsert переписує рядок правильним. Один зайвий
     * прохід на рядок — і дані вилікувані без ручного DELETE.
     */
    public function testMergeSkipsStaleMixedCaseRows(): void
    {
        ProductRoute::mergeUrlSlugAlias([
            $this->cacheRow('item-AS00004434', 'goods/item-AS00004434'),
            $this->cacheRow('product-0B200-03580600', 'gadgets/product-0B200-03580600'),
        ]);

        $this->assertFalse(ProductRoute::getUrlSlugAlias('item-as00004434'));
        $this->assertFalse(ProductRoute::getUrlSlugAlias('item-AS00004434'));
        $this->assertFalse(ProductRoute::getUrlSlugAlias('product-0b200-03580600'));
        $this->assertSame([], $this->storedAliases());
    }

    /**
     * Пропуск стосується лише рядків із бази. Коли модуль (Feeds, Rozetka,
     * GoogleMerchant) сам віддає зв'язку через setUrlSlugAlias(), це свіжі
     * дані з джерела — там великі літери легітимні й ключ просто нормалізується.
     */
    public function testDirectSetStillAcceptsMixedCaseUrls(): void
    {
        ProductRoute::setUrlSlugAlias('Item-AS00004434', 'goods/Item-AS00004434');

        $this->assertSame(
            'goods/Item-AS00004434',
            ProductRoute::getUrlSlugAlias('item-as00004434')
        );
    }

    /**
     * strtolower() не бере кирилицю, тому нормалізація мусить бути mb_-версією.
     */
    public function testCyrillicUrlIsMatchedRegardlessOfCase(): void
    {
        CategoryRoute::setUrlSlugAlias('Категорія-Тест', 'katalog/Категорія-Тест');

        $this->assertSame(
            'katalog/Категорія-Тест',
            CategoryRoute::getUrlSlugAlias('категорія-тест')
        );
    }

    /**
     * Форми URL, на яких дефект і спрацьовував: великі літери в артикулі —
     * рівно те, що генератори slug'ів лишають як є, а PHP-масив рахує іншим
     * ключем.
     */
    #[DataProvider('mixedCaseUrlProvider')]
    public function testMixedCaseUrlsHitTheCache(string $cachedUrl, string $slug, string $productUrl): void
    {
        ProductRoute::setUrlSlugAlias($cachedUrl, $slug);

        $this->assertSame($slug, ProductRoute::getUrlSlugAlias($productUrl));
    }

    public static function mixedCaseUrlProvider(): array
    {
        return [
            'item-AS00004434' => [
                'item-AS00004434',
                'goods/item-AS00004434',
                'item-as00004434',
            ],
            'item-AS00008245' => [
                'item-AS00008245',
                'goods/item-AS00008245',
                'item-as00008245',
            ],
            'product-0B200-03580600' => [
                'product-0B200-03580600',
                'gadgets/product-0B200-03580600',
                'product-0b200-03580600',
            ],
        ];
    }

    /**
     * Наслідок, який приймаємо свідомо: URL, що відрізняються лише регістром, —
     * це один запис, бо унікальний індекс у базі вважає так само. Другий запис
     * перекриває перший, а не живе поруч із ним.
     */
    public function testUrlsDifferingOnlyInCaseShareOneEntry(): void
    {
        ProductRoute::setUrlSlugAlias('Foo-1', 'first/Foo-1');
        ProductRoute::setUrlSlugAlias('foo-1', 'second/foo-1');

        $this->assertSame('second/foo-1', ProductRoute::getUrlSlugAlias('FOO-1'));
        $this->assertCount(1, $this->storedAliases());
    }

    public function testMissingUrlStillReturnsFalse(): void
    {
        $this->assertFalse(ProductRoute::getUrlSlugAlias('nothing-cached-here'));
    }

    /**
     * Порожній і null-урл приходять із порожніх полів сутностей. Нормалізація
     * не має ані падати, ані сипати deprecation'ами (mb_strtolower(null) на
     * PHP 8.1+ — саме такий випадок).
     */
    public function testNullAndEmptyUrlAreHandledWithoutDeprecation(): void
    {
        set_error_handler(static function ($no, $str): bool {
            throw new RuntimeException($str);
        }, E_DEPRECATED);

        try {
            ProductRoute::setUrlSlugAlias(null, 'some/slug');
            $this->assertSame('some/slug', ProductRoute::getUrlSlugAlias(null));
            $this->assertSame('some/slug', ProductRoute::getUrlSlugAlias(''));
        } finally {
            restore_error_handler();
        }
    }

    private function cacheRow(string $url, string $slugUrl): stdClass
    {
        $row = new stdClass();
        $row->url = $url;
        $row->slug_url = $slugUrl;

        return $row;
    }

    private function storedAliases(): array
    {
        foreach (self::ROUTE_CLASSES as $class) {
            $stored = (new ReflectionProperty($class, 'routeAliases'))->getValue();
            if (!empty($stored)) {
                return $stored;
            }
        }

        return [];
    }
}
