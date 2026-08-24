<?php

namespace Helpers;

use Okay\Core\Design;
use Okay\Core\EntityFactory;
use Okay\Core\Settings;
use Okay\Entities\AuthorsEntity;
use Okay\Entities\BlogEntity;
use Okay\Entities\BrandsEntity;
use Okay\Entities\ProductsEntity;
use Okay\Helpers\AuthorsHelper;
use Okay\Helpers\BlogHelper;
use Okay\Helpers\BrandsHelper;
use Okay\Helpers\CatalogHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * `page-all` знімав ліміт вибірки повністю: ліміт ставав рівним кількості
 * записів у лістингу. На каталозі це вичерпувало пам'ять PHP, і сторінка
 * віддавала текст фатала з кодом 200 — тобто для робота виглядала успішною.
 *
 * Однакова логіка живе в чотирьох хелперах ядра, скопійована разом із
 * коментарями, тож правку легко відкотити в одному з них і не помітити.
 * Тест міряє поведінку, а не текст: викликає paginate() і дивиться на ліміт,
 * який пішов у фільтр.
 */
class PaginatePageAllCapTest extends TestCase
{
    /**
     * Кожен хелпер збирається без конструктора: paginate() читає одну-дві
     * залежності, а повний конструктор тягне половину ядра.
     *
     * @return callable(array&, mixed): void
     */
    private function makePaginator(string $helperClass, $count, Design $design): callable
    {
        $reflection = new ReflectionClass($helperClass);
        $helper = $reflection->newInstanceWithoutConstructor();

        switch ($helperClass) {
            case CatalogHelper::class:
                $reflection->getProperty('settings')->setValue($helper, $this->stubSettings());
                $reflection->getProperty('productsEntity')
                    ->setValue($helper, $this->stubEntity(ProductsEntity::class, $count));
                break;
            case BrandsHelper::class:
                $reflection->getProperty('brandsEntity')
                    ->setValue($helper, $this->stubEntity(BrandsEntity::class, $count));
                break;
            case BlogHelper::class:
                $reflection->getProperty('entityFactory')
                    ->setValue($helper, $this->stubFactory(BlogEntity::class, $count));
                break;
            case AuthorsHelper::class:
                $reflection->getProperty('entityFactory')
                    ->setValue($helper, $this->stubFactory(AuthorsEntity::class, $count));
                break;
        }

        $method = $helperClass === BrandsHelper::class ? 'paginateBrands' : 'paginate';

        return static function (array &$filter, $currentPage) use ($helper, $method, $design) {
            $helper->$method(24, $currentPage, $filter, $design);
        };
    }

    private function stubSettings(): Settings
    {
        $settings = $this->createStub(Settings::class);
        $settings->method('get')->willReturn(null);

        return $settings;
    }

    private function stubEntity(string $entityClass, $count): object
    {
        $entity = $this->createStub($entityClass);
        $entity->method('count')->willReturn($count);

        return $entity;
    }

    private function stubFactory(string $entityClass, $count): EntityFactory
    {
        $entity = $this->stubEntity($entityClass, $count);

        $factory = $this->createStub(EntityFactory::class);
        $factory->method('get')->willReturn($entity);

        return $factory;
    }

    private function makeDesign(): Design
    {
        return new class extends Design {
            /** @var array<string, mixed> */
            public array $assigned = [];

            public function __construct()
            {
            }

            public function assign($var, $value, $dynamicJs = false)
            {
                $this->assigned[$var] = $value;
            }
        };
    }

    #[DataProvider('helpersProvider')]
    public function testPageAllStopsAtTheCap(string $helperClass): void
    {
        $design = $this->makeDesign();
        $paginate = $this->makePaginator($helperClass, 50000, $design);

        $filter = [];
        $paginate($filter, 'all');

        $this->assertSame(
            PAGE_ALL_MAX_ITEMS,
            $filter['limit'],
            'page-all знову тягне весь лістинг одним запитом'
        );
    }

    /**
     * Лістинг, менший за стелю, має віддаватись цілим — інакше стеля не
     * рятує від фатала, а мовчки ріже сторінки, які й раніше працювали.
     */
    #[DataProvider('helpersProvider')]
    public function testListingBelowTheCapIsUntouched(string $helperClass): void
    {
        $design = $this->makeDesign();
        $paginate = $this->makePaginator($helperClass, 7, $design);

        $filter = [];
        $paginate($filter, 'all');

        $this->assertSame(7, $filter['limit']);
        $this->assertTrue($design->assigned['is_all_pages']);
    }

    /**
     * Захист від нуля в ядрі був і до стелі; тест лише закріплює, що стеля
     * його не обійшла - min() з нулем дає нуль, а не від'ємне число.
     */
    #[DataProvider('helpersProvider')]
    public function testEmptyListingSurvivesPageAll(string $helperClass): void
    {
        $design = $this->makeDesign();
        $paginate = $this->makePaginator($helperClass, 0, $design);

        $filter = [];
        $paginate($filter, 'all');

        $this->assertSame(0, $filter['limit']);
        $this->assertEquals(0, $design->assigned['total_pages_num']);
    }

    /**
     * Стеля не має протікати на звичайні сторінки: там ліміт задає налаштування.
     */
    #[DataProvider('helpersProvider')]
    public function testOrdinaryPageKeepsItsOwnLimit(string $helperClass): void
    {
        $design = $this->makeDesign();
        $paginate = $this->makePaginator($helperClass, 50000, $design);

        $filter = [];
        $paginate($filter, 2);

        $this->assertSame(24, $filter['limit']);
        $this->assertSame(2, $filter['page']);
        $this->assertFalse($design->assigned['is_all_pages']);
    }

    /**
     * count() віддає те, що дав шар БД, а це залежить від налаштувань PDO:
     * при емуляції підготовлених запитів там числовий рядок. Стеля має
     * триматись однаково в обох випадках.
     */
    #[DataProvider('helpersProvider')]
    public function testCapHoldsWhenCountComesBackAsString(string $helperClass): void
    {
        $design = $this->makeDesign();
        $paginate = $this->makePaginator($helperClass, '50000', $design);

        $filter = [];
        $paginate($filter, 'all');

        $this->assertEquals(PAGE_ALL_MAX_ITEMS, $filter['limit']);
        $this->assertLessThanOrEqual(PAGE_ALL_MAX_ITEMS, (int) $filter['limit']);
    }

    /**
     * Головне, чого стеля не сміє зачепити: page-all лишається однією
     * сторінкою. Інакше ceil($count / стелю) робить із нього багатосторінковий
     * лістинг, а посилання пагінації ведуть на сторінки по products_num - тобто
     * віджет показує 8 сторінок там, де їх 326, і туди ж іде rel=next.
     */
    #[DataProvider('helpersProvider')]
    public function testCappedPageAllStaysASinglePage(string $helperClass): void
    {
        $design = $this->makeDesign();
        $paginate = $this->makePaginator($helperClass, 50000, $design);

        $filter = [];
        $paginate($filter, 'all');

        $this->assertEquals(
            1,
            $design->assigned['total_pages_num'],
            'page-all отримав вигадану пагінацію по стелі'
        );
    }

    /**
     * Лістинг, менший за стелю, теж одна сторінка - як було до правки.
     */
    #[DataProvider('helpersProvider')]
    public function testSmallPageAllIsAlsoASinglePage(string $helperClass): void
    {
        $design = $this->makeDesign();
        $paginate = $this->makePaginator($helperClass, 7, $design);

        $filter = [];
        $paginate($filter, 'all');

        $this->assertEquals(1, $design->assigned['total_pages_num']);
    }

    public function testCapIsDefinedAndPositive(): void
    {
        $this->assertTrue(defined('PAGE_ALL_MAX_ITEMS'));
        $this->assertGreaterThan(0, PAGE_ALL_MAX_ITEMS);
    }

    public static function helpersProvider(): array
    {
        return [
            'каталог' => [CatalogHelper::class],
            'блог'    => [BlogHelper::class],
            'автори'  => [AuthorsHelper::class],
            'бренди'  => [BrandsHelper::class],
        ];
    }
}
