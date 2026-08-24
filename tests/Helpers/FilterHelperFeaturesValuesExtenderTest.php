<?php

namespace Helpers;

use Okay\Core\EntityFactory;
use Okay\Core\Modules\Extender\ChainExtender;
use Okay\Core\Modules\Extender\ExtensionInterface;
use Okay\Entities\FeaturesValuesEntity;
use Okay\Helpers\FilterHelper;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Обидві гілки getFeaturesValues() мусять віддавати розширенню однакові
 * аргументи. Розходження не видно з коду розширення: на влучанні в кеш воно
 * дістане фільтр, на промаху — ArgumentCountError, тобто падає перший виклик
 * за запит, а не другий.
 */
class FilterHelperFeaturesValuesExtenderTest extends TestCase
{
    protected function tearDown(): void
    {
        (new ReflectionClass(ChainExtender::class))->getProperty('triggers')->setValue(null, []);
    }

    public function testCacheMissPassesTheFilterToTheExtension(): void
    {
        $extension = $this->registerExtension();
        $helper = $this->makeHelper();

        $helper->getFeaturesValues(['feature_id' => [7]]);

        $this->assertSame([['feature_id' => [7]]], $extension::$filters);
    }

    public function testCacheHitPassesTheSameFilter(): void
    {
        $extension = $this->registerExtension();
        $helper = $this->makeHelper();

        $helper->getFeaturesValues(['feature_id' => [7]]);
        $helper->getFeaturesValues(['feature_id' => [7]]);

        $this->assertSame(
            [['feature_id' => [7]], ['feature_id' => [7]]],
            $extension::$filters
        );
    }

    /**
     * Порожній результат — теж результат: без array_key_exists сутність
     * опитується щоразу, хоча відповідь уже відома.
     */
    public function testEmptyResultIsServedFromCache(): void
    {
        $helper = $this->makeHelper();

        $helper->getFeaturesValues(['feature_id' => [7]]);
        $helper->getFeaturesValues(['feature_id' => [7]]);

        $this->assertSame(1, FakeFeaturesValuesEntity::$findCalls);
    }

    private function makeHelper(): FilterHelper
    {
        FakeFeaturesValuesEntity::$findCalls = 0;

        $reflection = new ReflectionClass(FilterHelper::class);
        $helper = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('entityFactory')->setValue($helper, new FakeEntityFactory());

        return $helper;
    }

    /** @return class-string */
    private function registerExtension(): string
    {
        $extension = new class implements ExtensionInterface {
            /** @var array<int, array> фільтр, що дійшов до кожного виклику розширення */
            public static array $filters = [];

            public function extend($featuresValues, array $filter)
            {
                self::$filters[] = $filter;

                return $featuresValues;
            }
        };

        $class = get_class($extension);
        $class::$filters = [];

        (new ChainExtender())->newExtension(FilterHelper::class, 'getFeaturesValues', $class, 'extend');

        return $class;
    }
}

/** Сутність тут не потрібна: перевіряється передача аргументів, а не вибірка. */
class FakeFeaturesValuesEntity extends FeaturesValuesEntity
{
    public static int $findCalls = 0;

    public function __construct()
    {
    }

    public function addHighPriority($filterName)
    {
        return $this;
    }

    public function find(array $filter = [])
    {
        self::$findCalls++;

        return [];
    }
}

class FakeEntityFactory extends EntityFactory
{
    public function __construct()
    {
    }

    public function get($class)
    {
        return new FakeFeaturesValuesEntity();
    }
}
