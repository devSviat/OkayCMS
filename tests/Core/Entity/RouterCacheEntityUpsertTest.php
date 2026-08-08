<?php

namespace Core\Entity;

use Aura\SqlQuery\QueryFactory as AuraQueryFactory;
use Okay\Core\Modules\Extender\ChainExtender;
use Okay\Core\Modules\Extender\ExtensionInterface;
use Okay\Core\QueryFactory\Insert;
use Okay\Entities\RouterCacheEntity;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * RouterCacheEntity має власний add() рівно з однієї причини — щоб запис у кеш
 * був ідемпотентним. Простий INSERT, який дає CRUD::add(), ламається на
 * конкурентності: два запити, що одночасно генерують той самий іще не
 * закешований url, б'ються на унікальному ключі url_type, і той, хто програв,
 * кладе в лог 1062 Duplicate entry.
 *
 * Заміряно на живій базі з 7840 товарами, 200 незакешованих рядків: один
 * запит — 0 помилок, чотири паралельні — 600.
 *
 * Свідомо НЕ робимо тут двох речей, які колись пробували:
 *  - не зводимо url у нижній регістр — це справа реквестів адмінки, які
 *    закривають вхід, і CSV-імпорту через Translit::translit();
 *  - не перевіряємо форму slug — стратегія відповідає за те, що передає.
 */
class RouterCacheEntityUpsertTest extends TestCase
{
    public function testStatementUsesOnDuplicateKeyUpdate(): void
    {
        $statement = $this->add([
            'url'      => 'product-0b200-03580600',
            'slug_url' => 'gadgets/product-0b200-03580600',
            'type'     => 'product',
        ])->insert->getStatement();

        $this->assertStringContainsString('ON DUPLICATE KEY UPDATE', $statement);
    }

    /**
     * Пастка aura/sqlquery: onDuplicateKeyUpdateCol($col) без значення створює
     * плейсхолдер `:url__on_duplicate_key` і нічого в нього не біндить, тобто
     * список ['url','slug_url'] дав би невалідний запит. Значення мусять
     * приїхати парами ключ-значення.
     */
    public function testOnDuplicateKeyValuesAreActuallyBound(): void
    {
        $binds = $this->add([
            'url'      => 'product-0b200-03580600',
            'slug_url' => 'gadgets/product-0b200-03580600',
            'type'     => 'product',
        ])->insert->getBindValues();

        $this->assertArrayHasKey('url__on_duplicate_key', $binds);
        $this->assertArrayHasKey('slug_url__on_duplicate_key', $binds);
        $this->assertSame('product-0b200-03580600', $binds['url__on_duplicate_key']);
        $this->assertSame('gadgets/product-0b200-03580600', $binds['slug_url__on_duplicate_key']);
        $this->assertSame('product', $binds['type__on_duplicate_key']);
    }

    /**
     * Оновлюємо весь набір колонок, а не два імені списком: поле, додане
     * модулем через registerEntityField(), інакше лишалось би назавжди зі
     * значенням першої вставки.
     */
    public function testEveryPassedColumnIsAlsoUpdated(): void
    {
        $binds = $this->add([
            'url'      => 'product-x',
            'slug_url' => 'gadgets/product-x',
            'type'     => 'product',
        ])->insert->getBindValues();

        foreach (['url', 'slug_url', 'type'] as $col) {
            $this->assertArrayHasKey($col . '__on_duplicate_key', $binds, $col . ' має оновлюватись');
        }
    }

    /**
     * Регістр — не справа цього методу. Реквести адмінки й CSV-імпорт уже
     * зводять url у нижній, а тут значення має пройти як є: інакше товар, у
     * якого великі літери в урлі легітимні, отримав би в кеші не свій url.
     */
    public function testUrlIsPassedThroughUntouched(): void
    {
        $binds = $this->add([
            'url'      => 'Product-0B200',
            'slug_url' => 'gadgets/Product-0B200',
            'type'     => 'product',
        ])->insert->getBindValues();

        $this->assertSame('Product-0B200', $binds['url']);
        $this->assertSame('gadgets/Product-0B200', $binds['slug_url']);
    }

    /**
     * Database::query() ловить виняток і повертає false. Якщо add() віддавав би
     * беззастережне true, викликач не відрізнив би записаний рядок від
     * відхиленого.
     */
    public function testQueryResultIsReported(): void
    {
        $row = ['url' => 'product-x', 'slug_url' => 'gadgets/product-x', 'type' => 'product'];

        $this->assertTrue($this->add($row)->result);
        $this->assertFalse($this->add($row, queryResult: false)->result);
    }

    /**
     * Метод мусить лишатись розширюваним із модулів — це вимога до всіх
     * публічних методів сутностей. Перевіряємо поведінкою, а не текстом
     * джерела: реєструємо справжній ChainExtender і дивимось, чи спрацював.
     */
    public function testExtenderCanOverrideTheResult(): void
    {
        $extension = new \stdClass();
        $extension->class  = UpsertSpyExtension::class;
        $extension->method = 'onAdd';
        (new ReflectionProperty(ChainExtender::class, 'triggers'))
            ->setValue(null, [RouterCacheEntity::class . '::add' => [$extension]]);

        $original = ['url' => 'product-x', 'slug_url' => 'gadgets/product-x', 'type' => 'product'];
        $context  = $this->add($original);

        $this->assertSame('замінено екстендером', $context->result);
        $this->assertTrue(UpsertSpyExtension::$received[0], 'Перший аргумент — результат методу.');
        $this->assertSame($original, UpsertSpyExtension::$received[1], 'Далі — аргументи викликача.');
    }

    protected function setUp(): void
    {
        UpsertSpyExtension::$received = [];
    }

    protected function tearDown(): void
    {
        (new ReflectionProperty(ChainExtender::class, 'triggers'))->setValue(null, []);
        UpsertSpyExtension::$received = [];
    }

    /**
     * Збирає RouterCacheEntity без DI: конструктор Entity тягне сім сервісів із
     * контейнера, а для add() потрібні лише queryFactory і db.
     */
    private function add(array $object, bool $queryResult = true): object
    {
        $auraInsert = (new AuraQueryFactory('mysql'))->newInsert();

        $insert = (new \ReflectionClass(Insert::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty($insert, 'queryObject'))->setValue($insert, $auraInsert);

        $entity = (new \ReflectionClass(RouterCacheEntity::class))->newInstanceWithoutConstructor();

        (new ReflectionProperty($entity, 'queryFactory'))->setValue($entity, new class ($insert) {
            public function __construct(private readonly Insert $insert) {}
            public function newInsert(): Insert { return $this->insert; }
        });

        // Database::query() повертає true, а на впійманому винятку — false.
        $db = new class ($queryResult) {
            public bool $queried = false;
            public function __construct(private readonly bool $result) {}
            public function query($query, $debug = false) { $this->queried = true; return $this->result; }
        };
        (new ReflectionProperty($entity, 'db'))->setValue($entity, $db);

        $result = $entity->add($object);

        return new class ($insert, $db->queried, $result) {
            public function __construct(
                public readonly Insert $insert,
                public readonly bool $queried,
                public readonly mixed $result,
            ) {}
        };
    }
}

/**
 * Мінімальний ChainExtender-екстендер: запам'ятовує аргументи й підміняє
 * результат, щоб було видно, що ExtenderFacade реально відпрацював.
 */
class UpsertSpyExtension implements ExtensionInterface
{
    public static array $received = [];

    public function onAdd(...$args): string
    {
        self::$received = $args;

        return 'замінено екстендером';
    }
}
