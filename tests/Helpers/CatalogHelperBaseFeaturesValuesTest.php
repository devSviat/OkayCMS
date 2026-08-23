<?php

namespace Helpers;

use Okay\Helpers\CatalogHelper;
use Okay\Helpers\FilterHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Пиним сужение выборки в CatalogHelper::getBaseFeaturesValues().
 *
 * Потеря сужения не падает и не видна в разметке: FeaturesValuesEntity вернёт
 * значения всего каталога, вызывающий отбросит чужие циклом, страница
 * отрисуется так же — просто медленнее на порядок.
 */
class CatalogHelperBaseFeaturesValuesTest extends TestCase
{
    /**
     * Хелпер собирается без конструктора: getBaseFeaturesValues() читает только
     * filterHelper, а остальные зависимости тянут за собой половину ядра.
     */
    private function makeHelper(FilterHelper $filterHelper): CatalogHelper
    {
        $reflection = new ReflectionClass(CatalogHelper::class);
        $helper = $reflection->newInstanceWithoutConstructor();

        $reflection->getProperty('filterHelper')->setValue($helper, $filterHelper);

        return $helper;
    }

    /**
     * @param int[] $featureIds id свойств; ключами массива их делает FilterHelper::setFeatures()
     * @param array<string, mixed> $featuresValuesFilter
     */
    private function makeFilterHelper(array $featureIds, array $featuresValuesFilter = []): FilterHelper
    {
        $features = [];
        foreach ($featureIds as $id) {
            $features[$id] = (object) ['id' => $id];
        }

        return $this->makeFilterHelperFor($features, $featuresValuesFilter);
    }

    /**
     * @param object[] $features
     * @param array<string, mixed> $featuresValuesFilter
     */
    private function makeFilterHelperFor(array $features, array $featuresValuesFilter = []): FilterHelper
    {
        return new class ($features, $featuresValuesFilter) extends FilterHelper {
            /** @var array<string, mixed>|null */
            public $captured;

            /** @param object[] $features */
            public function __construct(private array $features, private array $featuresValuesFilter)
            {
            }

            public function getFeatures(): array
            {
                return $this->features;
            }

            public function getFeaturesValuesFilter(): array
            {
                return $this->featuresValuesFilter;
            }

            public function getKeyword(): ?string
            {
                return null;
            }

            public function getFeaturesValues(array $filter)
            {
                $this->captured = $filter;
                return [];
            }
        };
    }

    public function testNarrowsToFeaturesHeldByFilterHelper(): void
    {
        $filterHelper = $this->makeFilterHelper([7, 9, 11]);

        $this->makeHelper($filterHelper)->getBaseFeaturesValues();

        $this->assertSame([7, 9, 11], array_values($filterHelper->captured['feature_id']));
    }

    public function testKeepsFeatureIdSuppliedByCaller(): void
    {
        $filterHelper = $this->makeFilterHelper([7, 9]);

        $this->makeHelper($filterHelper)->getBaseFeaturesValues(['feature_id' => [42]]);

        $this->assertSame([42], $filterHelper->captured['feature_id']);
    }

    public function testNarrowsFilterTakenFromFilterHelper(): void
    {
        $filterHelper = $this->makeFilterHelper([7], ['category_id' => [1, 2]]);

        $this->makeHelper($filterHelper)->getBaseFeaturesValues();

        $this->assertSame([1, 2], $filterHelper->captured['category_id']);
        $this->assertSame([7], array_values($filterHelper->captured['feature_id']));
    }

    /**
     * Пустой массив в фильтре Entity отбрасывает условие целиком, поэтому
     * ключа не должно быть вовсе.
     */
    public function testAddsNoFeatureIdWithoutFeatures(): void
    {
        $filterHelper = $this->makeFilterHelper([]);

        $this->makeHelper($filterHelper)->getBaseFeaturesValues();

        $this->assertArrayNotHasKey('feature_id', $filterHelper->captured);
    }

    #[DataProvider('notNarrowedFeatureIdProvider')]
    public function testTreatsNullAndEmptyFeatureIdAsNotNarrowed(mixed $featureId): void
    {
        $filterHelper = $this->makeFilterHelper([7]);

        $this->makeHelper($filterHelper)->getBaseFeaturesValues(['feature_id' => $featureId]);

        $this->assertSame([7], array_values($filterHelper->captured['feature_id']));
    }

    /** @return array<string, array{0: mixed}> */
    public static function notNarrowedFeatureIdProvider(): array
    {
        return ['null' => [null], 'empty array' => [[]]];
    }

    /**
     * Ключи массива не источник истины: getFeatures() отдаёт результат через
     * ExtenderFacade, и модуль волен переложить массив как угодно.
     */
    public function testTakesIdFromFeatureObjectNotFromArrayKey(): void
    {
        $filterHelper = $this->makeFilterHelperFor([
            'brand' => (object) ['id' => 7],
            0       => (object) ['id' => 9],
        ]);

        $this->makeHelper($filterHelper)->getBaseFeaturesValues();

        $this->assertSame([7, 9], array_values($filterHelper->captured['feature_id']));
    }

    /**
     * Свойства держит FilterHelper. Обращение к $this->features внутри    /**
     * Свойства держит FilterHelper. Обращение к $this->features внутри
     * CatalogHelper молчит: empty() на необъявленном свойстве не предупреждает.
     */
    public function testReadsFeaturesThroughFilterHelperOnly(): void
    {
        $source = file_get_contents((new ReflectionClass(CatalogHelper::class))->getFileName());

        $this->assertDoesNotMatchRegularExpression('/\$this->features(?![A-Za-z_])/', $source);
    }
}
