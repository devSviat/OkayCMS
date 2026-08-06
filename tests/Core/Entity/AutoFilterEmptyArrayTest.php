<?php

namespace Core\Entity;

use Okay\Core\Entity\filter;
use PHPUnit\Framework\TestCase;

/**
 * Фіксатор свідомо збереженої поведінки ядра, а не опис бажаного.
 *
 * autoFilter() (Okay/Core/Entity/filter.php) на порожньому масиві не додає
 * умову взагалі, тож find(['id' => []]) означає «фільтра по id немає», а не
 * «нічого не знайдено», і віддає всю таблицю. Це пастка: три модулі фідів
 * саме на ній вироджувались у повний прохід каталогу.
 *
 * Лікувати вирішено викликачів, а не ядро: «порожній масив = порожній
 * результат» зачіпає кожне місце, що передає масив у фільтр, а перелічити їх
 * статично неможливо — дев-база на 227 товарів такий регрес сховає.
 *
 * Тест стоїть тут, щоб наступна спроба змінити ядро впала й вимагала розмови,
 * а не пройшла тихо. Якщо поведінку колись міняють свідомо — цей тест мусить
 * бути переписаний у тій самій зміні, разом з інвентарем викликачів.
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

    /**
     * Порожній рядок і 0 — не те саме, що порожній масив: вони умову додають.
     * Викидається саме [], і лише воно.
     */
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

    public function testUnknownFieldIsIgnored(): void
    {
        $entity = $this->makeEntity();
        $entity->applyFilter('not_a_field', [1, 2]);

        $this->assertSame([], $entity->conditions());
    }

    /**
     * Мінімальний носій трейта: справжня Entity тягне контейнер і БД, а тут
     * потрібен лише збір умов, які autoFilter() кладе в select.
     */
    private function makeEntity(): object
    {
        $select = new class {
            public array $where = [];
            public array $bound = [];
            public function where($condition) { $this->where[] = $condition; return $this; }
            public function bindValue($key, $value) { $this->bound[$key] = $value; return $this; }
        };

        return new class($select) {
            use filter;

            private $select;

            public function __construct($select)
            {
                $this->select = $select;
            }

            public function applyFilter($name, $value): void
            {
                // autoFilter() приватний у трейті, тож викликається зсередини
                // носія — так само, як це робить buildFilter().
                $this->autoFilter($name, $value);
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
