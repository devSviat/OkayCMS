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
    /**
     * Оновлення умовне: спрацьовує лише коли рядок конфліктує саме тим самим
     * url. Ключ префіксний — url(100), — тож у нього влучають і два різні
     * урли, що збігаються на перших 100 символах. Без цієї умови така вставка
     * затирала б slug_url чужого рядка, і сторінка, сайтмап та фіди назавжди
     * вели б одну сутність на чужу адресу. Перевірено на MariaDB 10.11:
     * рядок-жертва лишається недоторканим.
     */
    public function testUpdateFiresOnlyForTheSameUrl(): void
    {
        $statement = $this->add([
            'url'      => 'product-x',
            'slug_url' => 'gadgets/product-x',
            'type'     => 'product',
        ])->insert->getStatement();

        $this->assertStringContainsString(
            'IF(url = VALUES(url), VALUES(slug_url), slug_url)',
            $statement
        );
    }

    /**
     * url і type самі є унікальним ключем: на справжньому дублікаті вони вже
     * рівні, оновлювати нічого.
     */
    public function testKeyColumnsAreNeverUpdated(): void
    {
        $statement = $this->add([
            'url'      => 'product-x',
            'slug_url' => 'gadgets/product-x',
            'type'     => 'product',
        ])->insert->getStatement();

        $odku = substr($statement, strpos($statement, 'ON DUPLICATE KEY UPDATE'));

        $this->assertStringNotContainsString('`url` =', $odku);
        $this->assertStringNotContainsString('`type` =', $odku);
    }

    /**
     * Неключові оновлюються всі: поле, додане модулем через
     * registerEntityField(), інакше лишалось би назавжди зі значенням першої
     * вставки.
     */
    public function testNonKeyColumnsAreUpdated(): void
    {
        RouterCacheEntity::addField('weight');

        try {
            $odku = $this->add([
                'url'      => 'product-x',
                'slug_url' => 'gadgets/product-x',
                'type'     => 'product',
                'weight'   => 5,
            ])->insert->getStatement();

            $this->assertStringContainsString('`weight` = IF(url = VALUES(url), VALUES(weight), weight)', $odku);
        } finally {
            $fields = new ReflectionProperty(RouterCacheEntity::class, 'fields');
            $fields->setValue(null, array_values(array_diff(RouterCacheEntity::getFields(), ['weight'])));
        }
    }

    /**
     * Імена колонок ідуть у вираз текстом, тож усе, чого немає серед
     * оголошених полів, не має до нього доїжджати.
     */
    public function testUndeclaredColumnsNeverReachTheExpression(): void
    {
        $statement = $this->add([
            'url'      => 'product-x',
            'slug_url' => 'gadgets/product-x',
            'type'     => 'product',
            'evil)--'  => 'x',
        ])->insert->getStatement();

        $odku = substr($statement, strpos($statement, 'ON DUPLICATE KEY UPDATE'));

        $this->assertStringNotContainsString('evil', $odku);
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
