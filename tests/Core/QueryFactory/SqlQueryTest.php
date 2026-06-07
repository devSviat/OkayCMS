<?php

namespace Core\QueryFactory;

use Okay\Core\QueryFactory\SqlQuery;
use Aura\SqlQuery\QueryInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * aura/sqlquery 3.x added QueryInterface::resetFlags(); guards that the local
 * SqlQuery wrapper implements the full interface (otherwise it would be abstract
 * and uninstantiable) and that resetFlags() is chainable.
 */
class SqlQueryTest extends TestCase
{
    public function testImplementsQueryInterfaceFully(): void
    {
        $reflection = new ReflectionClass(SqlQuery::class);

        $this->assertTrue($reflection->isInstantiable(), 'SqlQuery must implement every QueryInterface method.');
        $this->assertTrue($reflection->implementsInterface(QueryInterface::class));
    }

    public function testResetFlagsIsChainable(): void
    {
        $query = new SqlQuery();

        $this->assertSame($query, $query->resetFlags());
    }
}
