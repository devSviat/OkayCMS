<?php

namespace Core\Entity;

use Okay\Core\Entity\CRUD;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * CRUD::add()/update() iterate the object's fields and run strtolower() on each
 * value to detect the literal 'now()'. A null field value triggered "Passing
 * null to parameter #1 ($string) of strtolower() is deprecated" on PHP 8.1+;
 * emitted mid-request (e.g. the UPDATE on first admin login) it broke headers
 * with "headers already sent". The (string) cast must keep both methods
 * deprecation-free for null values.
 */
class CrudTest extends TestCase
{
    #[DataProvider('methodProvider')]
    public function testNullFieldDoesNotTriggerDeprecation(string $method): void
    {
        $entity = $this->makeEntity();

        set_error_handler(static function ($no, $str): bool {
            throw new RuntimeException($str);
        }, E_DEPRECATED);

        try {
            if ($method === 'update') {
                $entity->update([1], (object)['some_field' => null]);
            } else {
                $entity->add((object)['some_field' => null]);
            }
        } finally {
            restore_error_handler();
        }

        $this->expectNotToPerformAssertions();
    }

    public static function methodProvider(): array
    {
        return [
            'add'    => ['add'],
            'update' => ['update'],
        ];
    }

    /**
     * Minimal object that uses the CRUD trait with stubbed collaborators, so
     * add()/update() reach the field loop without a real DB or ORM container.
     */
    private function makeEntity(): object
    {
        $query = new class {
            public function set($field, $value) { return $this; }
            public function into($table) { return $this; }
            public function cols($cols) { return $this; }
            public function table($table) { return $this; }
            public function where($where) { return $this; }
            public function bindValue($key, $value) { return $this; }
        };

        return new class($query) {
            use CRUD;

            protected $db;
            protected $queryFactory;

            public function __construct($query)
            {
                $this->db = new class {
                    public function query($q, $debug = false) { return null; }
                    public function insertId() { return 0; }
                };
                $this->queryFactory = new class($query) {
                    private $query;
                    public function __construct($query) { $this->query = $query; }
                    public function newInsert() { return $this->query; }
                    public function newUpdate() { return $this->query; }
                };
            }

            // Trait collaborators normally provided by the Entity class.
            public function getDescription($object, $usingLang = true) { return new stdClass(); }
            public function getTable() { return 'test_entity'; }
            public function getTableAlias() { return 't'; }
        };
    }
}
