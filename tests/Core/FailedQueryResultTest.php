<?php

namespace Core;

use Aura\Sql\ExtendedPdo;
use Aura\SqlQuery\QueryFactory as AuraQueryFactory;
use Okay\Core\Database;
use Okay\Core\Languages;
use Okay\Core\QueryFactory;
use Okay\Entities\FeedbacksEntity;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReflectionClass;

/**
 * Database::query() ловить помилку SQL і повертає false, але лишав результат
 * попереднього запиту: results() віддавав чужі рядки. CRUD::add() брав
 * insertId(), а після невдалої вставки це id попередньої вставки з'єднання,
 * update() повертав true за будь-якого результату.
 *
 * Запити будуються голою Aura: обгортка Okay\Core\QueryFactory у конструкторі
 * тягне Database через ServiceLocator, а в CI бази немає.
 */
class FailedQueryResultTest extends TestCase
{
    private function database(\PDOStatement $first): Database
    {
        $pdo = $this->createStub(ExtendedPdo::class);
        $calls = 0;
        $pdo->method('perform')->willReturnCallback(function () use (&$calls, $first) {
            if ($calls++ === 0) {
                return $first;
            }
            throw new \PDOException('Duplicate entry');
        });

        return new Database($pdo, new NullLogger(), ['prefix' => 'ok_'], new QueryFactory(new AuraQueryFactory('mysql')));
    }

    public function testFailedQueryDoesNotExposeThePreviousResult(): void
    {
        $previous = $this->createStub(\PDOStatement::class);
        $previous->method('fetchAll')->willReturn([(object) ['id' => 1, 'email' => 'other@example.com']]);
        $db = $this->database($previous);
        $aura = new AuraQueryFactory('mysql');

        $this->assertTrue($db->query($aura->newSelect()->cols(['1'])));
        $this->assertFalse($db->query($aura->newSelect()->cols(['2'])));

        $this->assertSame([], $db->results());
        $this->assertNull($db->result());
    }

    private function entity(bool $querySucceeds): FeedbacksEntity
    {
        $pdo = $this->createStub(ExtendedPdo::class);
        $pdo->method('perform')->willReturnCallback(function () use ($querySucceeds) {
            if (!$querySucceeds) {
                throw new \PDOException('Data too long for column');
            }
            return $this->createStub(\PDOStatement::class);
        });
        // Як справжній PDO після невдалої вставки: id попередньої вставки з'єднання.
        $pdo->method('lastInsertId')->willReturn('42');
        $db = new Database($pdo, new NullLogger(), ['prefix' => 'ok_'], new QueryFactory(new AuraQueryFactory('mysql')));
        $lang = $this->createStub(Languages::class);
        $lang->method('getDescription')->willReturn((object) []);

        // Лише те, що CRUD бере з фабрики, — голими запитами Aura.
        $queryFactory = new class {
            private AuraQueryFactory $aura;
            public function __construct() { $this->aura = new AuraQueryFactory('mysql'); }
            public function newInsert() { return $this->aura->newInsert(); }
            public function newUpdate() { return $this->aura->newUpdate(); }
        };

        $entity = (new ReflectionClass(FeedbacksEntity::class))->newInstanceWithoutConstructor();
        foreach (['db' => $db, 'queryFactory' => $queryFactory, 'lang' => $lang] as $prop => $value) {
            (new ReflectionClass($entity))->getProperty($prop)->setValue($entity, $value);
        }

        return $entity;
    }

    public function testFailedInsertIsNotReportedWithAStaleId(): void
    {
        $this->assertFalse($this->entity(false)->add(['name' => 'x', 'email' => 'a@b.c', 'message' => 'm']));
    }

    public function testFailedUpdateIsNotReportedAsSuccess(): void
    {
        $this->assertFalse($this->entity(false)->update(5, ['name' => 'x']));
    }

    /** Контроль: успішні вставка й оновлення працюють як і раніше. */
    public function testSuccessfulWritesAreUnchanged(): void
    {
        $this->assertSame(42, $this->entity(true)->add(['name' => 'x', 'email' => 'a@b.c', 'message' => 'm']));
        $this->assertTrue($this->entity(true)->update(5, ['name' => 'x']));
    }
}
