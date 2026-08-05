<?php

namespace Admin\Helpers;

use Okay\Admin\Helpers\BackendCategoryStatsHelper;
use Okay\Core\EntityFactory;
use Okay\Core\Request;
use Okay\Entities\CategoriesEntity;
use Okay\Entities\ReportStatEntity;
use PHPUnit\Framework\TestCase;

/**
 * buildFilter() перевіряв $date_from і $date_to, а присвоював $dateFrom і
 * $dateTo. Обидві умови завжди були хибними, тож обраний адміном період не
 * доходив до вибірки — сторінка мовчки показувала продажі за весь час.
 */
class BackendCategoryStatsHelperTest extends TestCase
{
    protected function setUp(): void
    {
        $_GET = [];
    }

    protected function tearDown(): void
    {
        $_GET = [];
    }

    public function testDateFromReachesTheFilter()
    {
        $_GET = ['date_from' => '2026-08-01'];

        $filter = $this->makeHelper()->buildFilter();

        $this->assertSame('2026-08-01 00:00:01', $filter['date_from']);
    }

    public function testDateToCoversTheWholeDay()
    {
        $_GET = ['date_to' => '2026-08-31'];

        $filter = $this->makeHelper()->buildFilter();

        $this->assertSame('2026-08-31 23:59:00', $filter['date_to']);
    }

    public function testEmptyPeriodLeavesFilterOpen()
    {
        $filter = $this->makeHelper()->buildFilter();

        $this->assertArrayNotHasKey('date_from', $filter);
        $this->assertArrayNotHasKey('date_to', $filter);
    }

    /**
     * strtotime() на сміття віддає false, а date() з false — це 1970 рік:
     * вибірка мовчки поїхала б замість того, щоб лишитись без обмеження.
     */
    public function testUnparsableDateIsIgnored()
    {
        $_GET = ['date_from' => 'позавчора', 'date_to' => '31/02/2026'];

        $filter = $this->makeHelper()->buildFilter();

        $this->assertArrayNotHasKey('date_from', $filter);
        $this->assertArrayNotHasKey('date_to', $filter);
    }

    /**
     * ?date_from[]=x — масив, а strtotime() приймає лише рядок.
     */
    public function testArrayInsteadOfDateDoesNotBreak()
    {
        $_GET = ['date_from' => ['2026-08-01']];

        $filter = $this->makeHelper()->buildFilter();

        $this->assertArrayNotHasKey('date_from', $filter);
    }

    public function testKnownCategoryLimitsFilterToItsChildren()
    {
        $_GET = ['category' => '17'];

        $filter = $this->makeHelper((object)['id' => 17, 'children' => [17, 18]])->buildFilter();

        $this->assertSame([17, 18], $filter['category_id']);
    }

    /**
     * get() на неіснуючу категорію віддає false. Звернення до ->children на
     * false — Warning, тобто вивід ще до заголовків сторінки.
     */
    public function testUnknownCategoryIsIgnoredWithoutWarning()
    {
        $_GET = ['category' => '999999'];

        $filter = $this->makeHelper(false)->buildFilter();

        $this->assertArrayNotHasKey('category_id', $filter);
    }

    private function makeHelper($category = false): BackendCategoryStatsHelper
    {
        $categoriesEntity = $this->createStub(CategoriesEntity::class);
        $categoriesEntity->method('get')->willReturn($category);

        $entityFactory = $this->createStub(EntityFactory::class);
        $entityFactory->method('get')->willReturnCallback(
            fn ($class) => $class === CategoriesEntity::class
                ? $categoriesEntity
                : $this->createStub(ReportStatEntity::class)
        );

        return new BackendCategoryStatsHelper($entityFactory, new Request());
    }
}
