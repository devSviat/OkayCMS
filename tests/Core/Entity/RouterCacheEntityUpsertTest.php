<?php

namespace Core\Entity;

use Aura\SqlQuery\QueryFactory as AuraQueryFactory;
use Okay\Core\Modules\Extender\ChainExtender;
use Okay\Core\Modules\Extender\ExtensionInterface;
use Okay\Core\QueryFactory\Insert;
use Okay\Entities\RouterCacheEntity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Власний add() у RouterCacheEntity існує рівно заради ідемпотентності.
 * Простий INSERT із CRUD::add() ламається на конкурентності: два запити, що
 * одночасно кешують той самий url, б'ються на ключі url_type, і той, хто
 * програв, кладе в лог 1062. Заміряно на живій базі: 200 незакешованих
 * рядків, один запит — 0 помилок, чотири паралельні — 600.
 *
 * Свідомо не робимо тут двох речей: не зводимо регістр (це справа входу) і не
 * перевіряємо форму slug (за це відповідає стратегія).
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

        $this->assertArrayHasKey('slug_url__on_duplicate_key', $binds);
        $this->assertSame('gadgets/product-0b200-03580600', $binds['slug_url__on_duplicate_key']);
    }

    /**
     * url і type самі є унікальним ключем. На справжньому дублікаті вони вже
     * рівні, а на колізії префіксного індексу url(100) оновлення url затерло б
     * чужий рядок замість того, щоб відхилити вставку — і два товари почали б
     * по черзі витісняти один одного з кешу назавжди.
     */
    public function testKeyColumnsAreNeverUpdated(): void
    {
        $binds = $this->add([
            'url'      => 'product-x',
            'slug_url' => 'gadgets/product-x',
            'type'     => 'product',
        ])->insert->getBindValues();

        $this->assertArrayNotHasKey('url__on_duplicate_key', $binds);
        $this->assertArrayNotHasKey('type__on_duplicate_key', $binds);
    }

    /**
     * А неключові оновлюються всі: поле, додане модулем через
     * registerEntityField(), інакше лишалось би назавжди зі значенням першої
     * вставки.
     */
    public function testNonKeyColumnsAreUpdated(): void
    {
        $binds = $this->add([
            'url'      => 'product-x',
            'slug_url' => 'gadgets/product-x',
            'type'     => 'product',
            'weight'   => 5,
        ])->insert->getBindValues();

        $this->assertArrayHasKey('slug_url__on_duplicate_key', $binds);
        $this->assertArrayHasKey('weight__on_duplicate_key', $binds);
    }

    /**
     * Порожній slug стратегія віддає, коли не знайшла сутності. До upsert така
     * вставка просто падала й чинний рядок лишався; тепер вона затерла б його
     * порожнім значенням, тож до бази не доходить зовсім.
     */
    #[DataProvider('emptySlugProvider')]
    public function testEmptySlugIsNeverWritten(array $object): void
    {
        $context = $this->add($object);

        $this->assertFalse($context->queried, 'Порожній slug не має доходити до бази.');
        $this->assertFalse($context->result, 'add() має повідомити, що нічого не записано.');
    }

    public static function emptySlugProvider(): array
    {
        return [
            'порожній рядок' => [['url' => 'product-x', 'slug_url' => '',   'type' => 'product']],
            'null'           => [['url' => 'product-x', 'slug_url' => null, 'type' => 'product']],
            'відсутній'      => [['url' => 'product-x', 'type' => 'product']],
        ];
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
