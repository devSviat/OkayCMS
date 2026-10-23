<?php

namespace Security;

use Okay\Core\QueryFactory;
use Okay\Core\QueryFactory\SqlQuery;
use Okay\Core\Security\ManagerLoginKey;
use PHPUnit\Framework\TestCase;

/**
 * Ключ рахує база вагою в колації стовпця. Тут — який запит іде в базу й як
 * знімається вага пробілу; збіг з рівністю в колації перевіряє
 * ManagerLoginKeyDatabaseTest на живій базі.
 */
class ManagerLoginKeyTest extends TestCase
{
    /** @var string[] */
    private array $statements = [];
    /** @var array<string, mixed> */
    private array $bound = [];

    public function testPadSpaceCollationDropsTrailingSpaceWeight(): void
    {
        $key = $this->key('utf8mb4_unicode_ci', 'ABC10209', '0209')->of(' Admin ');

        $this->assertSame('w:ABC1', $key);
        $this->assertSame(
            "SELECT HEX(WEIGHT_STRING(CONVERT(:login USING utf8mb4) COLLATE utf8mb4_unicode_ci)) AS w,"
            . " HEX(WEIGHT_STRING(CONVERT(' ' USING utf8mb4) COLLATE utf8mb4_unicode_ci)) AS sp",
            $this->statements[1]
        );
        $this->assertSame(' Admin ', $this->bound['login'], 'логін іде параметром, без змін');
    }

    /**
     * Знімаються одиниці ваги пробілу, вирівняні на їхню довжину: «0209» усередині
     * чужої одиниці (…0020 9…) — не пробіл.
     */
    public function testTrailingSpaceWeightIsTrimmedByWholeUnits(): void
    {
        $this->assertSame('106A1002', ManagerLoginKey::trimTrailingSpaceWeight('106A100202090209', '0209'));
        $this->assertSame('106A', ManagerLoginKey::trimTrailingSpaceWeight('106A', '0209'));
        $this->assertSame('', ManagerLoginKey::trimTrailingSpaceWeight('02090209', '0209'));
        $this->assertSame('A0209', ManagerLoginKey::trimTrailingSpaceWeight('A0209', '0209'), 'одиниці не вирівняні');
        $this->assertSame('000041', ManagerLoginKey::trimTrailingSpaceWeight('000041000020000020', '000020'));
    }

    public function testNoPadCollationKeepsTrailingSpaces(): void
    {
        $key = $this->key('utf8mb4_0900_ai_ci', 'ABC10209', '0209')->of('admin ');

        $this->assertSame('w:ABC10209', $key);
        $this->assertStringContainsString('COLLATE utf8mb4_0900_ai_ci', $this->statements[1]);
    }

    /** Ім'я колації потрапляє в текст запиту, тож будь-що, крім імені колації, відкидається. */
    public function testSuspiciousCollationNameIsNotInjected(): void
    {
        $this->key("utf8mb4_bin) UNION SELECT password FROM ok_managers -- ", 'ABC1', '0209')->of('admin');

        $this->assertStringContainsString('COLLATE utf8mb4_unicode_ci', $this->statements[1]);
        $this->assertStringNotContainsString('UNION', $this->statements[1]);
    }

    public function testCollationIsReadOncePerProcess(): void
    {
        $key = $this->key('utf8mb4_unicode_ci', 'ABC1', '0209');
        $key->of('a');
        $key->of('b');

        $this->assertCount(3, $this->statements);
    }

    public function testWithoutAWeightTheKeyIsStillStable(): void
    {
        $key = $this->key('utf8mb4_unicode_ci', null, null);

        $this->assertSame($key->of('Admin '), $key->of('admin'));
    }

    private function key(string $collation, ?string $weight, ?string $space): ManagerLoginKey
    {
        $factory = $this->createStub(QueryFactory::class);
        $factory->method('newSqlQuery')->willReturnCallback(function () use ($collation, $weight, $space) {
            $query = $this->createStub(SqlQuery::class);
            $query->method('setStatement')->willReturnCallback(function ($statement) use ($query) {
                $this->statements[] = (string)$statement;

                return $query;
            });
            $query->method('bindValue')->willReturnCallback(function ($name, $value) use ($query) {
                $this->bound[$name] = $value;

                return $query;
            });
            $query->method('result')->willReturnCallback(fn($column) => $column === 'Collation' ? $collation : null);
            $query->method('results')->willReturn($weight === null ? [] : [(object)['w' => $weight, 'sp' => $space]]);

            return $query;
        });

        return new ManagerLoginKey($factory);
    }
}
