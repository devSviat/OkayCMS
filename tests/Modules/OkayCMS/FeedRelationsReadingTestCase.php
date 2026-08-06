<?php

namespace Modules\OkayCMS;

use Okay\Core\EntityFactory;
use Okay\Core\QueryFactory;
use Okay\Core\Request;
use Okay\Helpers\ProductsHelper;
use PHPUnit\Framework\TestCase;

/**
 * BackendGoogleMerchantHelper, BackendHotlineHelper і BackendRozetkaHelper —
 * побайтово однакові, різняться лише іменами класів. Тому набір перевірок
 * спільний, а підкласи лише називають свої класи: інакше три копії тесту
 * розійшлися б із часом і дефект повернувся б у той модуль, який забули.
 *
 * Накриті два дефекти:
 *  - getList(['id' => []]) віддавав увесь каталог, бо autoFilter() мовчки
 *    викидає порожній масив (Okay/Core/Entity/filter.php);
 *  - одна сторінка адмінки читала таблицю зв'язків чотири рази, щоразу ще
 *    й із окремим COUNT(*) заради ['limit' => count()].
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
     * Заглушки, а не моки з expects(): усі очікування виражені звичайними
     * assert'ами по записаних викликах, тож видно і кількість, і аргументи.
     */
    protected function makeHelper(array $relations, array $products = [])
    {
        $this->findCalls = [];
        $this->getListCalls = [];

        $relationsEntity = $this->createStub($this->relationsEntityClass());
        $relationsEntity->method('noLimit')->willReturnSelf();
        $relationsEntity->method('find')->willReturnCallback(
            function (array $filter = []) use ($relations) {
                $this->findCalls[] = $filter;
                return $relations;
            }
        );

        $feedsEntity = $this->createStub($this->feedsEntityClass());
        $feedsEntity->method('noLimit')->willReturnSelf();
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

        // Request не підміняється: у нього є власний метод method(), а PHPUnit
        // не вміє дублювати класи з таким іменем методу. Справжній Request тут
        // безпечний — перевіряються лише читання, які його не торкаються.
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
     * Головний дефект: порожній перелік id — це «нічого не закріплено», а не
     * «фільтра немає». Свіжовстановлений модуль не має жодного зв'язку типу
     * product, тож обидва виклики вироджувались у повний прохід каталогу.
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

    /**
     * Зв'язок може пережити свій товар: до правки шаблон отримував null у
     * масиві, а PHP — попередження про невизначений індекс.
     */
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

    /**
     * Чотири методи, які адмінка викликає підряд, мусять коштувати один запит,
     * а не чотири SELECT плюс чотири COUNT.
     */
    public function testTheFourReadersShareOneRelationsQuery(): void
    {
        $helper = $this->makeHelper([$this->relation(1, 10, 'category')]);

        $helper->getAllRelatedCategoriesIds();
        $helper->getAllRelatedBrandsIds();
        $helper->getAllRelatedProducts();
        $helper->getAllNotRelatedProducts();

        $this->assertCount(1, $this->findCalls);
    }

    /**
     * ['limit' => $entity->count()] — це два запити замість одного, та ще й із
     * вікном між ними, у яке паралельна вставка губить рядки.
     */
    public function testRelationsAreReadWithoutACountBasedLimit(): void
    {
        $helper = $this->makeHelper([]);

        $helper->getAllRelatedCategoriesIds();

        $this->assertSame([[]], $this->findCalls);
    }

    /**
     * Кеш живе рівно один запит і не переживає запис: інакше сторінка після
     * збереження показувала б стан до нього.
     */
    public function testAWriteInvalidatesTheCachedRelations(): void
    {
        $helper = $this->makeHelper([$this->relation(1, 10, 'category')]);

        $helper->getAllRelatedCategoriesIds();
        $helper->updateRelatedCategories([]);
        $helper->getAllRelatedCategoriesIds();

        $this->assertCount(2, $this->findCalls);
    }
}
