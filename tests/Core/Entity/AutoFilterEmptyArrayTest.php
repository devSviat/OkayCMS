<?php

namespace Core\Entity;

use Okay\Core\Entity\filter;
use PHPUnit\Framework\TestCase;

/**
 * Фіксатор свідомо збереженої поведінки ядра, а не опис бажаного.
 *
 * autoFilter() на порожньому масиві не додає умову взагалі, тож
 * find(['id' => []]) віддає всю таблицю. Лікувати вирішено викликачів:
 * «порожній масив = порожній результат» зачіпає кожне місце, що передає масив
 * у фільтр, а перелічити їх статично неможливо.
 *
 * Тест стоїть тут, щоб наступна спроба змінити ядро впала й вимагала розмови.
 */
class AutoFilterEmptyArrayTest extends TestCase
{
    public function testEmptyArrayAddsNoCondition(): void
    {
        $entity = $this->makeEntity();
        $entity->applyFilter('id', []);

        $this->assertSame([], $entity->conditions());
    }

    public function testNonEmptyArrayAddsAnInCondition(): void
    {
        $entity = $this->makeEntity();
        $entity->applyFilter('id', [1, 2]);

        $this->assertSame(['t.id IN (:magic_filter_id)'], $entity->conditions());
        $this->assertSame(['magic_filter_id' => [1, 2]], $entity->bindings());
    }

    public function testScalarAddsAnEqualityCondition(): void
    {
        $entity = $this->makeEntity();
        $entity->applyFilter('id', 7);

        $this->assertSame(['t.id = :magic_filter_id'], $entity->conditions());
    }

    /** Викидається саме [] — порожній рядок і 0 умову додають. */
    public function testFalsyScalarsStillAddACondition(): void
    {
        foreach (['', 0, '0'] as $value) {
            $entity = $this->makeEntity();
            $entity->applyFilter('id', $value);

            $this->assertSame(
                ['t.id = :magic_filter_id'],
                $entity->conditions(),
                'значення ' . var_export($value, true) . ' мусить лишати умову'
            );
        }
    }

    /**
     * Без цієї перевірки можна було вимкнути «магічний фільтр» у buildFilter()
     * цілком — і весь набір тестів лишався зеленим.
     */
    public function testBuildFilterReachesTheAutoFilter(): void
    {
        $entity = $this->makeEntity();
        $entity->applyFilterThroughBuildFilter(['id' => [1, 2], 'name' => 'x']);

        $this->assertSame(
            ['t.id IN (:magic_filter_id)', 't.name = :magic_filter_name'],
            $entity->conditions()
        );
    }

    public function testBuildFilterDropsAnEmptyArrayToo(): void
    {
        $entity = $this->makeEntity();
        $entity->applyFilterThroughBuildFilter(['id' => []]);

        $this->assertSame([], $entity->conditions());
    }

    public function testUnknownFieldIsIgnored(): void
    {
        $entity = $this->makeEntity();
        $entity->applyFilter('not_a_field', [1, 2]);

        $this->assertSame([], $entity->conditions());
    }

    /** Мінімальний носій трейта: справжня Entity тягне контейнер і БД. */
    private function makeEntity(): object
    {
        $select = new class {
            public array $where = [];
            public array $bound = [];
            public function where($condition) { $this->where[] = $condition; return $this; }
            public function bindValue($key, $value) { $this->bound[$key] = $value; return $this; }
        };

        // Реєстр фільтрів від модулів: у справжньої Entity його дає
        // ServiceLocator, тут достатньо «жодного модульного фільтра немає».
        $modulesFilters = new class {
            public function hasFilter($entityClass, $filterName) { return false; }
        };

        return new class($select, $modulesFilters) {
            use filter;

            private $select;
            private $modulesFilters;

            public function __construct($select, $modulesFilters)
            {
                $this->select = $select;
                $this->modulesFilters = $modulesFilters;
            }

            public function applyFilter($name, $value): void
            {
                // autoFilter() приватний у трейті, тож викликається зсередини
                // носія — так само, як це робить buildFilter().
                $this->autoFilter($name, $value);
            }

            /** Той самий шлях, яким ходить справжній find(). */
            public function applyFilterThroughBuildFilter(array $filter): void
            {
                $this->buildFilter($filter);
            }

            // Колаборанти buildFilter(), які зазвичай дає клас Entity.
            public function orderFilterByPriority(array $filter = [])
            {
                return $filter;
            }

            public function conditions(): array
            {
                return $this->select->where;
            }

            public function bindings(): array
            {
                return $this->select->bound;
            }

            // Колаборанти, які зазвичай дає клас Entity.
            public function getLangFields() { return []; }
            public function getFields() { return ['id', 'name']; }
            public function getTableAlias() { return 't'; }
        };
    }
}
