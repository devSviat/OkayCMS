<?php

namespace Modules\OkayCMS;

use Okay\Core\EntityFactory;
use Okay\Core\QueryFactory;
use Okay\Core\Request;
use Okay\Helpers\ProductsHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Три хелпери побайтово однакові, тож набір перевірок спільний, а підкласи
 * лише називають свої класи: три копії тесту розійшлися б із часом.
 *
 * Накриті два дефекти: getList(['id' => []]) віддавав увесь каталог, і одна
 * сторінка читала таблицю зв'язків чотири рази з окремим COUNT(*) на кожен.
 */
abstract class FeedRelationsReadingTestCase extends TestCase
{
    /** @return class-string Хелпер, що перевіряється */
    abstract protected function helperClass(): string;

    /** @return class-string Сутність фідів модуля */
    abstract protected function feedsEntityClass(): string;

    /** @return class-string Сутність зв'язків модуля */
    abstract protected function relationsEntityClass(): string;

    /** Фільтри, з якими викликали find() по таблиці зв'язків. */
    private array $findCalls = [];

    /** Фільтри, з якими викликали ProductsHelper::getList(). */
    private array $getListCalls = [];

    /**
     * Обмеження на 100 рядків живе не у фільтрі, а в buildPagination(), тож
     * заглушка його не бачить: без прямого підрахунку прибраний noLimit()
     * лишив би тест зеленим.
     *
     * @var array<string,int>
     */
    private array $noLimitCalls = [];

    /** Заглушки, а не моки: очікування виражені assert'ами по записаних викликах. */
    protected function makeHelper(array $relations, array $products = [])
    {
        $this->findCalls = [];
        $this->getListCalls = [];
        $this->noLimitCalls = ['relations' => 0, 'feeds' => 0];

        $relationsEntity = $this->createStub($this->relationsEntityClass());
        $relationsEntity->method('noLimit')->willReturnCallback(
            function () use (&$relationsEntity) {
                $this->noLimitCalls['relations']++;
                return $relationsEntity;
            }
        );
        $relationsEntity->method('find')->willReturnCallback(
            function (array $filter = []) use ($relations) {
                $this->findCalls[] = $filter;
                return $relations;
            }
        );

        $feedsEntity = $this->createStub($this->feedsEntityClass());
        $feedsEntity->method('noLimit')->willReturnCallback(
            function () use (&$feedsEntity) {
                $this->noLimitCalls['feeds']++;
                return $feedsEntity;
            }
        );
        $feedsEntity->method('find')->willReturn([]);

        $entityFactory = $this->createStub(EntityFactory::class);
        $entityFactory->method('get')->willReturnCallback(fn($class) => match ($class) {
            $this->relationsEntityClass() => $relationsEntity,
            $this->feedsEntityClass()     => $feedsEntity,
            default                       => $this->createStub($class),
        });

        $productsHelper = $this->createStub(ProductsHelper::class);
        $productsHelper->method('getList')->willReturnCallback(
            function (array $filter = []) use ($products) {
                $this->getListCalls[] = $filter;
                return $products;
            }
        );

        $helperClass = $this->helperClass();

        // Request не підміняється: PHPUnit не дублює класи з методом method().
        // Справжній тут безпечний — читання його не торкаються.
        return new $helperClass(
            $entityFactory,
            $this->createStub(QueryFactory::class),
            new Request(),
            $productsHelper
        );
    }

    protected function relation(int $feedId, int $entityId, string $type, int $include = 1): object
    {
        return (object)[
            'feed_id'     => $feedId,
            'entity_id'   => $entityId,
            'entity_type' => $type,
            'include'     => $include,
        ];
    }

    /**
     * Свіжовстановлений модуль не має жодного зв'язку типу product, тож обидва
     * виклики вироджувались у повний прохід каталогу.
     */
    public function testNoProductRelationsMeansNoCatalogQuery(): void
    {
        $helper = $this->makeHelper([$this->relation(1, 10, 'category')]);

        $this->assertSame([], $helper->getAllRelatedProducts());
        $this->assertSame([], $helper->getAllNotRelatedProducts());
        $this->assertSame([], $this->getListCalls, 'каталог не мусить читатись узагалі');
    }

    public function testRelatedProductsAreFetchedByTheirIdsOnly(): void
    {
        $helper = $this->makeHelper(
            [
                $this->relation(1, 5, 'product', 1),
                $this->relation(2, 7, 'product', 1),
                $this->relation(1, 9, 'product', 0),
            ],
            [5 => (object)['id' => 5], 7 => (object)['id' => 7]]
        );

        $related = $helper->getAllRelatedProducts();

        $this->assertSame([['id' => [5, 7]]], $this->getListCalls);
        $this->assertSame([5], array_map(fn($p) => $p->id, $related[1]));
        $this->assertSame([7], array_map(fn($p) => $p->id, $related[2]));
    }

    public function testNotRelatedProductsAreFetchedByTheirIdsOnly(): void
    {
        $helper = $this->makeHelper(
            [
                $this->relation(1, 5, 'product', 1),
                $this->relation(1, 9, 'product', 0),
            ],
            [9 => (object)['id' => 9]]
        );

        $notRelated = $helper->getAllNotRelatedProducts();

        $this->assertSame([['id' => [9]]], $this->getListCalls);
        $this->assertSame([9], array_map(fn($p) => $p->id, $notRelated[1]));
    }

    /** Зв'язок може пережити свій товар. */
    public function testRelationsPointingAtAMissingProductAreSkipped(): void
    {
        $helper = $this->makeHelper(
            [
                $this->relation(1, 5, 'product', 1),
                $this->relation(1, 999, 'product', 1),
            ],
            [5 => (object)['id' => 5]]
        );

        $related = $helper->getAllRelatedProducts();

        $this->assertSame([5], array_map(fn($p) => $p->id, $related[1]));
    }

    public function testCategoriesAndBrandsAreGroupedByFeed(): void
    {
        $helper = $this->makeHelper([
            $this->relation(1, 10, 'category'),
            $this->relation(1, 11, 'category'),
            $this->relation(2, 12, 'category'),
            $this->relation(1, 20, 'brand'),
        ]);

        $this->assertSame([1 => [10, 11], 2 => [12]], $helper->getAllRelatedCategoriesIds());
        $this->assertSame([1 => [20]], $helper->getAllRelatedBrandsIds());
    }

    /** Чотири методи підряд мусять коштувати один запит, а не чотири плюс COUNT. */
    public function testTheFourReadersShareOneRelationsQuery(): void
    {
        $helper = $this->makeHelper([$this->relation(1, 10, 'category')]);

        $helper->getAllRelatedCategoriesIds();
        $helper->getAllRelatedBrandsIds();
        $helper->getAllRelatedProducts();
        $helper->getAllNotRelatedProducts();

        $this->assertCount(1, $this->findCalls);
    }

    /** ['limit' => count()] — два запити з вікном, у яке губляться рядки. */
    public function testRelationsAreReadWithoutACountBasedLimit(): void
    {
        $helper = $this->makeHelper([]);

        $helper->getAllRelatedCategoriesIds();

        $this->assertSame([[]], $this->findCalls);
    }

    /** Порожній фільтр — половина справи: ліміт живе не в ньому, а в пагінації. */
    public function testRelationsAreReadWithoutTheDefaultPageSize(): void
    {
        $helper = $this->makeHelper([$this->relation(1, 10, 'category')]);

        $helper->getAllRelatedCategoriesIds();

        $this->assertSame(1, $this->noLimitCalls['relations'], 'читання зв\'язків мусить іти через noLimit()');
    }

    /** Те саме для читання фідів у методах запису. */
    public function testFeedsAreReadWithoutTheDefaultPageSize(): void
    {
        $helper = $this->makeHelper([]);

        $helper->updateRelatedProducts();
        $helper->updateNotRelatedProducts();

        $this->assertSame(2, $this->noLimitCalls['feeds'], 'читання фідів мусить іти через noLimit()');
    }

    /** Кеш не переживає запис: інакше сторінка після збереження показує старе. */
    public function testAWriteInvalidatesTheCachedRelations(): void
    {
        $helper = $this->makeHelper([$this->relation(1, 10, 'category')]);

        $helper->getAllRelatedCategoriesIds();
        $helper->updateRelatedCategories([]);
        $helper->getAllRelatedCategoriesIds();

        $this->assertCount(2, $this->findCalls);
    }

    /**
     * Контролер знімав категорії й бренди прямим викликом на сутності — повз
     * скидання кешу. Тепер це методи хелпера, і вони мусять кеш скидати.
     */
    #[DataProvider('feedScopedRemovalProvider')]
    public function testRemovingAFeedScopeInvalidatesTheCachedRelations(string $method): void
    {
        $helper = $this->makeHelper([$this->relation(1, 10, 'category')]);

        $helper->getAllRelatedCategoriesIds();
        $helper->$method(1);
        $helper->getAllRelatedCategoriesIds();

        $this->assertCount(2, $this->findCalls);
    }

    public static function feedScopedRemovalProvider(): array
    {
        return [
            'categories' => ['removeAllCategoriesByFeedId'],
            'brands'     => ['removeAllBrandsByFeedId'],
        ];
    }
}
