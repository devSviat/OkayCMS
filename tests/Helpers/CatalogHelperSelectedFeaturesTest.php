<?php

namespace Helpers;

use Okay\Core\Design;
use Okay\Core\Settings;
use Okay\Entities\ProductsEntity;
use Okay\Helpers\CatalogHelper;
use Okay\Helpers\FilterHelper;
use Okay\Helpers\MetaRobotsHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Набір, який assignCatalogDataProcedure() віддає MetaRobotsHelper, мусить
 * покривати те, що вибране в URL. Інакше getCatalogRobots() кидає
 * «Wrong feature id/value» — HTTP 500 на посиланні, яке зробив сам сайт.
 *
 * Друга річ, яку тут пришпилено: 0 у ліміті свойств — це «без обмеження».
 * Порожнє поле в адмінці зберігається саме нулем, і без цього блок фільтра
 * зникав із /all-products, пошуку і /brands цілком.
 */
class CatalogHelperSelectedFeaturesTest extends TestCase
{
    private const SELECTED_FEATURE = 5;
    private const SELECTED_VALUE   = 99;

    private SpyFilterHelper $filterHelper;
    private SpyMetaRobotsHelper $metaRobotsHelper;

    /**
     * Значення вибране, але звужене по in_stock: у вибірках його немає, і
     * повернути його можна лише окремим запитом по id.
     */
    public function testSelectedValueSurvivesStockNarrowing(): void
    {
        $helper = $this->makeHelper(
            categoryValues: [],
            selectedValues: [$this->value(self::SELECTED_VALUE, self::SELECTED_FEATURE)]
        );

        $helper->assignCatalogDataProcedure(
            $this->productsFilterWithSelection(),
            [self::SELECTED_FEATURE => $this->feature(self::SELECTED_FEATURE)],
            [],
            []
        );

        $available = $this->metaRobotsHelper->available;

        $this->assertArrayHasKey(self::SELECTED_FEATURE, $available);
        $this->assertArrayHasKey(
            self::SELECTED_VALUE,
            $available[self::SELECTED_FEATURE]->features_values
        );
    }

    /** Свойство з вибраним значенням не прибирає жодна з двох петель. */
    public function testSelectedFeatureIsNeverPruned(): void
    {
        $helper = $this->makeHelper(categoryValues: [], selectedValues: []);

        $helper->assignCatalogDataProcedure(
            $this->productsFilterWithSelection(),
            [self::SELECTED_FEATURE => $this->feature(self::SELECTED_FEATURE)],
            [],
            []
        );

        $this->assertArrayHasKey(self::SELECTED_FEATURE, $this->metaRobotsHelper->available);
    }

    #[DataProvider('limitsThatMeanNoLimit')]
    public function testNonPositiveLimitDoesNotPruneFeatures(?int $limit): void
    {
        $features = [];
        $values   = [];
        foreach ([11, 12, 13] as $featureId) {
            $features[$featureId] = $this->feature($featureId);
            $values[]             = $this->value($featureId * 10, $featureId);
        }

        $helper = $this->makeHelper(categoryValues: $values, selectedValues: []);

        $helper->assignCatalogDataProcedure([], $features, [], [], $limit);

        $this->assertSame([11, 12, 13], array_keys($this->metaRobotsHelper->available));
    }

    /** @return array<string, array{0: int|null}> */
    public static function limitsThatMeanNoLimit(): array
    {
        return [
            'порожнє поле в адмінці' => [0],
            'ключа немає в базі'     => [null],
        ];
    }

    /** Додатне обмеження лишається обмеженням. */
    public function testPositiveLimitStillPrunes(): void
    {
        $features = [];
        $values   = [];
        foreach ([11, 12, 13] as $featureId) {
            $features[$featureId] = $this->feature($featureId);
            $values[]             = $this->value($featureId * 10, $featureId);
        }

        $helper = $this->makeHelper(categoryValues: $values, selectedValues: []);

        $helper->assignCatalogDataProcedure([], $features, [], [], 2);

        $this->assertSame([11, 12], array_keys($this->metaRobotsHelper->available));
    }

    /** Порожній набір вибраного не має доходити до вибірки: там він означав би «усе». */
    public function testNothingSelectedMeansNoQuery(): void
    {
        $helper = $this->makeHelper([], []);

        $this->assertSame([], $helper->getSelectedFeaturesValues([]));
        $this->assertSame([], $helper->getSelectedFeaturesValues(['features' => []]));
        $this->assertNull($this->filterHelper->requestedIdsFilter);
    }

    public function testSelectedIdsAreTakenFromValueKeys(): void
    {
        $helper = $this->makeHelper([], []);

        $helper->getSelectedFeaturesValues($this->productsFilterWithSelection());

        $this->assertSame([self::SELECTED_VALUE], $this->filterHelper->requestedIdsFilter);
    }

    /** @return array<string, mixed> */
    private function productsFilterWithSelection(): array
    {
        // FilterHelper::getCurrentFeatures() кладе сюди translit значенням, а id — ключем.
        return ['features' => [self::SELECTED_FEATURE => [self::SELECTED_VALUE => 'chasha']]];
    }

    private function feature(int $id): object
    {
        return (object) ['id' => $id];
    }

    private function value(int $id, int $featureId): object
    {
        return (object) ['id' => $id, 'feature_id' => $featureId];
    }

    /**
     * @param object[] $categoryValues значення, які віддає звужена вибірка
     * @param object[] $selectedValues значення, які віддає вибірка по id
     */
    private function makeHelper(array $categoryValues, array $selectedValues): CatalogHelper
    {
        $this->filterHelper     = new SpyFilterHelper($categoryValues, $selectedValues);
        $this->metaRobotsHelper = new SpyMetaRobotsHelper();

        $helper = new class extends CatalogHelper {
            public function __construct()
            {
            }

            /** ServiceLocator тут недоступний, а до перевірки цей блок стосунку не має. */
            public function getOtherFilters(array $filter)
            {
                return [];
            }
        };

        $reflection = new ReflectionClass(CatalogHelper::class);
        $reflection->getProperty('filterHelper')->setValue($helper, $this->filterHelper);
        $reflection->getProperty('metaRobotsHelper')->setValue($helper, $this->metaRobotsHelper);
        $reflection->getProperty('settings')->setValue($helper, new SpySettings());
        $reflection->getProperty('design')->setValue($helper, new SpyDesign());
        $reflection->getProperty('productsEntity')->setValue($helper, new SpyProductsEntity());

        return $helper;
    }
}

class SpyFilterHelper extends FilterHelper
{
    /** @var int[]|null id, які запитали окремою вибіркою */
    public ?array $requestedIdsFilter = null;

    /**
     * @param object[] $categoryValues
     * @param object[] $selectedValues
     */
    public function __construct(
        private array $categoryValues,
        private array $selectedValues
    ) {
    }

    public function getFeaturesValues(array $filter)
    {
        if (isset($filter['id'])) {
            $this->requestedIdsFilter = $filter['id'];

            return $this->selectedValues;
        }

        return $this->categoryValues;
    }

    public function getFeaturesValuesFilter(): array
    {
        return [];
    }

    public function getFeatures(): array
    {
        return [];
    }

    public function getKeyword(): ?string
    {
        return null;
    }

    public function prepareFilterGetFeaturesValues(array $productsFilter = [], ?array $featuresValuesFilter = null, ?string $missingProducts = null): array
    {
        return [];
    }

    public function setFeatureValue($featureValue)
    {
        return $featureValue;
    }

}

class SpyMetaRobotsHelper extends MetaRobotsHelper
{
    /** @var array<int, object> */
    public array $available = [];

    public function __construct()
    {
    }

    public function setAvailableFeatures(array $features): self
    {
        $this->available = $features;

        return $this;
    }
}

class SpySettings extends Settings
{
    public function __construct()
    {
    }

    public function get($param)
    {
        return null;
    }
}

class SpyDesign extends Design
{
    public function __construct()
    {
    }

    public function assign($var, $value, $dynamicJs = false)
    {
        return $this;
    }
}

class SpyProductsEntity extends ProductsEntity
{
    public function __construct()
    {
    }

    public function getPriceRange(array $filter = [])
    {
        return (object) ['min' => 0, 'max' => 0];
    }
}
