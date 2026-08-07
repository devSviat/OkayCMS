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
 * ok_router_cache не має колонки `id`, тож CRUD::add() однаково завжди повертав
 * false, а простий INSERT давав 1062 Duplicate entry щоразу, коли URL
 * відрізнявся від закешованого лише регістром: унікальний індекс url_type живе
 * в utf8mb4_general_ci. Свій add() зводить url у нижній регістр і пише через
 * ON DUPLICATE KEY UPDATE, тож застарілий рядок лагодиться сам.
 */
class RouterCacheEntityAddTest extends TestCase
{
    public function testUrlIsStoredInLowerCase(): void
    {
        $binds = $this->addAndGetBindValues([
            'url'      => 'asus-0B200-03580600',
            'slug_url' => 'laptops/asus-0b200-03580600',
            'type'     => 'product',
        ]);

        $this->assertSame('asus-0b200-03580600', $binds['url']);
    }

    public function testStatementUsesOnDuplicateKeyUpdate(): void
    {
        $statement = $this->addAndGetStatement([
            'url'      => 'asus-0B200-03580600',
            'slug_url' => 'laptops/asus-0b200-03580600',
            'type'     => 'product',
        ]);

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
        $binds = $this->addAndGetBindValues([
            'url'      => 'asus-0B200-03580600',
            'slug_url' => 'laptops/asus-0b200-03580600',
            'type'     => 'product',
        ]);

        $this->assertArrayHasKey('url__on_duplicate_key', $binds);
        $this->assertArrayHasKey('slug_url__on_duplicate_key', $binds);
        $this->assertSame('asus-0b200-03580600', $binds['url__on_duplicate_key']);
        $this->assertSame('laptops/asus-0b200-03580600', $binds['slug_url__on_duplicate_key']);
    }

    /**
     * slug_url — це `$category->url . '/' . $product->url`, тобто вже такий, як
     * у джерелі. Зводити його в нижній регістр не можна: це змінило б URL
     * товарів, у яких великі літери легітимні.
     */
    public function testSlugUrlKeepsItsOriginalCase(): void
    {
        $binds = $this->addAndGetBindValues([
            'url'      => 'delonghi-as00004434',
            'slug_url' => 'coffee-machines/Delonghi-AS00004434',
            'type'     => 'product',
        ]);

        $this->assertSame('coffee-machines/Delonghi-AS00004434', $binds['slug_url']);
    }

    /**
     * У таблиці лише три колонки. Усе інше не має доїжджати до cols() — ані
     * `id`, якого в таблиці немає, ані будь-що, що прилетіло з викликача.
     */
    public function testUnknownColumnsNeverReachTheQuery(): void
    {
        $insert = $this->add([
            'id'       => 5,
            'url'      => 'asus-0b200-03580600',
            'slug_url' => 'laptops/asus-0b200-03580600',
            'type'     => 'product',
            'evil_col' => 'DROP TABLE',
        ])->insert;

        $statement = $insert->getStatement();
        $binds     = $insert->getBindValues();

        $this->assertStringNotContainsString('evil_col', $statement);
        $this->assertArrayNotHasKey('evil_col', $binds);
        $this->assertArrayNotHasKey('id', $binds);
        $this->assertSame(['url', 'slug_url', 'type'], array_keys(
            array_intersect_key($binds, array_flip(['url', 'slug_url', 'type']))
        ));
    }

    public function testTypeIsPassedThroughUntouched(): void
    {
        $binds = $this->addAndGetBindValues([
            'url'      => 'kavovarky',
            'slug_url' => 'katalog/kavovarky',
            'type'     => 'category',
        ]);

        $this->assertSame('category', $binds['type']);
    }

    /**
     * Порожній slug кешувати нема сенсу — getUrlSlugAlias() однаково вважає
     * такий запис відсутнім, бо перевіряє через !empty(). А з появою
     * ON DUPLICATE KEY UPDATE такий запис став ще й руйнівним: там, де INSERT
     * раніше просто падав і лишав чинний рядок недоторканим, upsert затер би
     * його порожнім значенням. Порожній slug виникає, коли сутність не
     * знайшлась: `$product->url` на false дає null.
     */
    #[DataProvider('incompleteObjectProvider')]
    public function testIncompleteRowIsNeverWritten(array $object): void
    {
        $context = $this->add($object);

        $this->assertFalse($context->queried, 'Неповний рядок не має доходити до бази.');
        $this->assertFalse($context->result, 'add() має повідомити, що нічого не записано.');
    }

    public static function incompleteObjectProvider(): array
    {
        return [
            'порожній slug_url'  => [['url' => 'asus-0b200', 'slug_url' => '',   'type' => 'product']],
            'slug_url = null'    => [['url' => 'asus-0b200', 'slug_url' => null, 'type' => 'product']],
            'slug_url відсутній' => [['url' => 'asus-0b200', 'type' => 'product']],
            'порожній url'       => [['url' => '',   'slug_url' => 'laptops/asus', 'type' => 'product']],
            'url = null'         => [['url' => null, 'slug_url' => 'laptops/asus', 'type' => 'product']],
            'url відсутній'      => [['slug_url' => 'laptops/asus', 'type' => 'product']],
            'порожній type'      => [['url' => 'asus-0b200', 'slug_url' => 'laptops/asus', 'type' => '']],
            'type відсутній'     => [['url' => 'asus-0b200', 'slug_url' => 'laptops/asus']],
        ];
    }

    /**
     * Метод мусить лишатись розширюваним із модулів — це вимога до всіх
     * публічних методів сутностей і хелперів. Перевіряємо поведінкою, а не
     * текстом джерела: реєструємо справжній ChainExtender і дивимось, чи він
     * спрацював.
     */
    public function testExtenderCanOverrideTheResult(): void
    {
        $this->registerChainExtension(RouterCacheEntity::class, 'add', AddSpyExtension::class, 'onAdd');

        $context = $this->add([
            'url'      => 'asus-0B200-03580600',
            'slug_url' => 'laptops/asus-0b200-03580600',
            'type'     => 'product',
        ]);

        $this->assertSame('замінено екстендером', $context->result);
        $this->assertTrue(AddSpyExtension::$received[0], 'Екстендер має отримати результат методу першим аргументом.');
    }

    /**
     * func_get_args() у PHP 8 віддає поточні значення параметрів, тож якби
     * add() перезаписував $object, екстендери бачили б відфільтровані дані
     * замість того, що передав викликач.
     */
    public function testExtenderReceivesTheCallersOriginalArgument(): void
    {
        $this->registerChainExtension(RouterCacheEntity::class, 'add', AddSpyExtension::class, 'onAdd');

        $original = [
            'url'      => 'asus-0B200-03580600',
            'slug_url' => 'laptops/asus-0b200-03580600',
            'type'     => 'product',
            'evil_col' => 'DROP TABLE',
        ];

        $this->add($original);

        $this->assertSame($original, AddSpyExtension::$received[1]);
    }

    protected function setUp(): void
    {
        AddSpyExtension::$received = [];
    }

    protected function tearDown(): void
    {
        (new ReflectionProperty(ChainExtender::class, 'triggers'))->setValue(null, []);
        AddSpyExtension::$received = [];
    }

    private function registerChainExtension(string $class, string $method, string $extender, string $extenderMethod): void
    {
        $extension = new \stdClass();
        $extension->class = $extender;
        $extension->method = $extenderMethod;

        $property = new ReflectionProperty(ChainExtender::class, 'triggers');
        $property->setValue(null, ["{$class}::{$method}" => [$extension]]);
    }

    /**
     * Збирає RouterCacheEntity без DI: конструктор Entity тягне сім сервісів із
     * контейнера, а для add() потрібні лише queryFactory і db.
     */
    private function add(array $object): object
    {
        $auraInsert = (new AuraQueryFactory('mysql'))->newInsert();

        $insert = (new \ReflectionClass(Insert::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty($insert, 'queryObject'))->setValue($insert, $auraInsert);

        $entity = (new \ReflectionClass(RouterCacheEntity::class))->newInstanceWithoutConstructor();

        (new ReflectionProperty($entity, 'queryFactory'))->setValue($entity, new class ($insert) {
            public function __construct(private readonly Insert $insert) {}
            public function newInsert(): Insert { return $this->insert; }
        });

        $db = new class {
            public bool $queried = false;
            public function query($query, $debug = false) { $this->queried = true; return null; }
            public function insertId() { return 0; }
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

    private function addAndGetBindValues(array $object): array
    {
        return $this->add($object)->insert->getBindValues();
    }

    private function addAndGetStatement(array $object): string
    {
        return $this->add($object)->insert->getStatement();
    }
}

/**
 * Мінімальний ChainExtender-екстендер: запамʼятовує аргументи й підміняє
 * результат, щоб було видно, що ExtenderFacade реально відпрацював.
 */
class AddSpyExtension implements ExtensionInterface
{
    public static array $received = [];

    public function onAdd(...$args): string
    {
        self::$received = $args;

        return 'замінено екстендером';
    }
}
