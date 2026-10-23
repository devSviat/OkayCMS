<?php

namespace Security;

use Okay\Core\QueryFactory;
use Okay\Core\QueryFactory\SqlQuery;
use Okay\Core\Security\ManagerLoginKey;
use PHPUnit\Framework\TestCase;

/**
 * Ключ рахує база вагою в колації стовпця. Тут — який запит іде в базу;
 * що вага збігається з рівністю в колації, перевірено на MariaDB наживо.
 */
class ManagerLoginKeyTest extends TestCase
{
    /** @var string[] */
    private array $statements = [];
    /** @var array<string, mixed> */
    private array $bound = [];

    public function testPadSpaceCollationIgnoresOnlyTrailingSpaces(): void
    {
        $key = $this->key('utf8mb4_unicode_ci', 'ABC1')->of(' Admin ');

        $this->assertSame('w:ABC1', $key);
        $this->assertSame(
            'SELECT HEX(WEIGHT_STRING(RTRIM(CONVERT(:login USING utf8mb4)) COLLATE utf8mb4_unicode_ci)) AS w',
            $this->statements[1]
        );
        $this->assertSame(' Admin ', $this->bound['login'], 'логін іде параметром, без змін');
    }

    public function testNoPadCollationKeepsTrailingSpaces(): void
    {
        $this->key('utf8mb4_0900_ai_ci', 'ABC1')->of('admin');

        $this->assertStringNotContainsString('RTRIM', $this->statements[1]);
        $this->assertStringContainsString('COLLATE utf8mb4_0900_ai_ci', $this->statements[1]);
    }

    /** Ім'я колації потрапляє в текст запиту, тож будь-що, крім імені колації, відкидається. */
    public function testSuspiciousCollationNameIsNotInjected(): void
    {
        $this->key("utf8mb4_bin) UNION SELECT password FROM ok_managers -- ", 'ABC1')->of('admin');

        $this->assertStringContainsString('COLLATE utf8mb4_unicode_ci', $this->statements[1]);
        $this->assertStringNotContainsString('UNION', $this->statements[1]);
    }

    public function testCollationIsReadOncePerProcess(): void
    {
        $key = $this->key('utf8mb4_unicode_ci', 'ABC1');
        $key->of('a');
        $key->of('b');

        $this->assertCount(3, $this->statements);
    }

    public function testWithoutAWeightTheKeyIsStillStable(): void
    {
        $key = $this->key('utf8mb4_unicode_ci', null);

        $this->assertSame($key->of('Admin '), $key->of('admin'));
    }

    private function key(string $collation, ?string $weight): ManagerLoginKey
    {
        $factory = $this->createStub(QueryFactory::class);
        $factory->method('newSqlQuery')->willReturnCallback(function () use ($collation, $weight) {
            $query = $this->createStub(SqlQuery::class);
            $query->method('setStatement')->willReturnCallback(function ($statement) use ($query) {
                $this->statements[] = (string)$statement;

                return $query;
            });
            $query->method('bindValue')->willReturnCallback(function ($name, $value) use ($query) {
                $this->bound[$name] = $value;

                return $query;
            });
            $query->method('result')->willReturnCallback(fn($column) => $column === 'Collation' ? $collation : $weight);

            return $query;
        });

        return new ManagerLoginKey($factory);
    }
}
