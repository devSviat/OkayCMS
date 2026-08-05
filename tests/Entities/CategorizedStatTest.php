<?php

namespace Entities;

use Okay\Core\QueryFactory;
use Okay\Entities\ReportStatEntity;
use PHPUnit\Framework\TestCase;

/**
 * getCategorizedStat() зводить покупки в масив за category_id. Категорія
 * береться через LEFT JOIN, тож покупка видаленого товару дає category_id
 * = NULL, а GROUP BY зводить усі такі покупки в один рядок.
 *
 * На PHP 8.5 $result[null] — це Deprecated, і на сторінці
 * CategoryStatsAdmin він друкувався раніше за заголовки: далі йшло
 * "Cannot modify header information".
 */
class CategorizedStatTest extends TestCase
{
    public function testRowWithoutCategoryIsNotUsedAsKey()
    {
        $entity = $this->entityReturning([
            (object)['category_id' => null, 'amount' => '298', 'price' => '15000'],
            (object)['category_id' => '17', 'amount' => '4', 'price' => '2000'],
        ]);

        $result = $entity->getCategorizedStat();

        $this->assertSame([17], array_keys($result), 'у зведенні по категоріях є лише категорії');
    }

    /**
     * Порожній рядок як ключ — це те, у що PHP мовчки перетворював NULL до
     * 8.5. Дерево категорій такого ключа не має, тож рядок нікуди не
     * потрапляв, лише ламав вивід.
     */
    public function testEmptyKeyDoesNotAppearInstead()
    {
        $entity = $this->entityReturning([
            (object)['category_id' => null, 'amount' => '298', 'price' => '15000'],
        ]);

        $result = $entity->getCategorizedStat();

        $this->assertSame([], $result);
    }

    public function testRowsWithCategoryAreKeptAsIs()
    {
        $rows = [
            (object)['category_id' => '17', 'amount' => '4', 'price' => '2000'],
            (object)['category_id' => '42', 'amount' => '1', 'price' => '500'],
        ];

        $result = $this->entityReturning($rows)->getCategorizedStat();

        $this->assertSame([17, 42], array_keys($result));
        $this->assertSame($rows[0], $result[17]);
        $this->assertSame($rows[1], $result[42]);
    }

    private function entityReturning(array $rows): ReportStatEntity
    {
        $entity = (new \ReflectionClass(ReportStatEntity::class))->newInstanceWithoutConstructor();

        $queryFactory = $this->createStub(QueryFactory::class);
        $queryFactory->method('newSelect')->willReturn(new CategorizedStatSelectStub());

        $this->setProperty($entity, 'queryFactory', $queryFactory);
        $this->setProperty($entity, 'db', new CategorizedStatDatabaseStub($rows));

        return $entity;
    }

    private function setProperty($entity, string $name, $value): void
    {
        (new \ReflectionProperty(\Okay\Core\Entity\Entity::class, $name))->setValue($entity, $value);
    }
}

/**
 * Збирання запиту тут не перевіряється, а справжній Select у конструкторі
 * тягне з контейнера Database. Тому — фасад, що приймає будь-який виклик
 * ланцюжка (from/cols/join/groupBy/where/bindValue).
 */
class CategorizedStatSelectStub
{
    public function __call($method, $arguments)
    {
        return $this;
    }
}

/**
 * Не нащадок Database навмисне: її деструктор відʼєднується від PDO, тож
 * навіть заглушка вимагає живого зʼєднання. Entity звертається лише до
 * query() і results().
 */
class CategorizedStatDatabaseStub
{
    private array $rows;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function query($query, $debug = false)
    {
        return true;
    }

    public function results($field = null, $mapped = null)
    {
        return $this->rows;
    }
}
