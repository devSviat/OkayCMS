<?php

namespace Core\Entity;

use Aura\SqlQuery\QueryFactory as AuraQueryFactory;
use Okay\Core\QueryFactory\Insert;
use Okay\Entities\RouterCacheEntity;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
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
        ]);

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
     * Метод мусить лишатись розширюваним із модулів — це вимога до всіх
     * публічних методів сутностей і хелперів.
     */
    public function testAddStaysExtendable(): void
    {
        $method = new ReflectionMethod(RouterCacheEntity::class, 'add');

        // Для методу з трейту getFileName() вказав би на CRUD.php — тоді
        // перевірка нижче читала б чуже джерело й нічого не гарантувала.
        $this->assertSame(
            (new \ReflectionClass(RouterCacheEntity::class))->getFileName(),
            $method->getFileName(),
            'add() має бути перевизначений у самій сутності, а не взятий із трейту CRUD.'
        );

        $source = implode('', array_slice(
            file($method->getFileName()),
            $method->getStartLine() - 1,
            $method->getEndLine() - $method->getStartLine() + 1
        ));

        $this->assertStringContainsString('ExtenderFacade::execute', $source);
    }

    /**
     * Збирає RouterCacheEntity без DI: конструктор Entity тягне сім сервісів із
     * контейнера, а для add() потрібні лише queryFactory і db.
     */
    private function add(array $object): Insert
    {
        $auraInsert = (new AuraQueryFactory('mysql'))->newInsert();

        $insert = (new \ReflectionClass(Insert::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty($insert, 'queryObject'))->setValue($insert, $auraInsert);

        $entity = (new \ReflectionClass(RouterCacheEntity::class))->newInstanceWithoutConstructor();

        (new ReflectionProperty($entity, 'queryFactory'))->setValue($entity, new class ($insert) {
            public function __construct(private readonly Insert $insert) {}
            public function newInsert(): Insert { return $this->insert; }
        });

        (new ReflectionProperty($entity, 'db'))->setValue($entity, new class {
            public function query($query, $debug = false) { return null; }
            public function insertId() { return 0; }
        });

        $entity->add($object);

        return $insert;
    }

    private function addAndGetBindValues(array $object): array
    {
        return $this->add($object)->getBindValues();
    }

    private function addAndGetStatement(array $object): string
    {
        return $this->add($object)->getStatement();
    }
}
