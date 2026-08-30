<?php

namespace Entities;

use Aura\SqlQuery\Common\SelectInterface;
use Aura\SqlQuery\QueryFactory as AuraQueryFactory;
use Okay\Entities\FeaturesEntity;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Регресія тиха: блок фільтрів лишається правильним, лише повільнішим, — тож
 * ловить її тільки перевірка згенерованого SQL.
 *
 * Вибірки будуються голою Aura, а не Okay\Core\QueryFactory: обгортка в
 * конструкторі тягне Database через ServiceLocator, а в CI бази немає.
 */
class FeaturesBrandSubqueryTest extends TestCase
{
    public function testBrandSubqueryIsNotDistinct(): void
    {
        self::assertStringNotContainsString(
            'DISTINCT',
            self::buildBrandFilterSql(),
            'підзапит filter__brand знову з DISTINCT — повний скан замість індексу'
        );
    }

    /** Контроль: якби заглушка не віддавала DISTINCT, перевірка вище була б зеленою ні на чому. */
    public function testStubReallyCarriesDistinct(): void
    {
        self::assertStringContainsString('DISTINCT', (string) self::productsSelect());
    }

    public function testBrandSubqueryStillSelectsFeatureId(): void
    {
        self::assertStringContainsString('feature_id', self::buildBrandFilterSql());
    }

    /** Дублі підзапиту стануть видимими, щойно він потрапить у проєкцію. */
    public function testSubqueryStaysOutOfOuterProjection(): void
    {
        $sql = self::buildBrandFilterSql();
        $projection = substr($sql, 0, strpos($sql, 'FROM'));

        self::assertStringNotContainsString('filter__brand__p', $projection);
    }

    private static function buildBrandFilterSql(): string
    {
        $own = (new AuraQueryFactory('mysql'))->newSelect();
        $own->from('__features AS f')->cols(['f.id']);

        $entity = new ReflectionClass(FeaturesEntity::class);
        $features = $entity->newInstanceWithoutConstructor();

        $entity->getProperty('select')->setValue($features, $own);
        $entity->getProperty('entity')->setValue($features, new FeaturesFakeEntityFactory(self::productsSelect()));

        $entity->getMethod('filter__brand')->invoke($features, true);

        return (string) $own;
    }

    /** Вибірка товарів такою, якою її віддає ProductsEntity::getSelect() — уже з DISTINCT. */
    private static function productsSelect(): SelectInterface
    {
        $select = (new AuraQueryFactory('mysql'))->newSelect();
        $select
            ->distinct(true)
            ->from('__products AS p')
            ->cols(['p.id', 'r.slug_url'])
            ->join('LEFT', '__router_cache AS r', 'r.url=p.url AND r.type="product"');

        return $select;
    }
}

class FeaturesFakeEntityFactory
{
    /** @var SelectInterface */
    private $productsSelect;

    public function __construct(SelectInterface $productsSelect)
    {
        $this->productsSelect = $productsSelect;
    }

    public function get($class): object
    {
        return new FeaturesFakeProductsEntity($this->productsSelect);
    }
}

class FeaturesFakeProductsEntity
{
    /** @var SelectInterface */
    private $select;

    public function __construct(SelectInterface $select)
    {
        $this->select = $select;
    }

    public function getSelect(array $filter = []): SelectInterface
    {
        return $this->select;
    }
}
