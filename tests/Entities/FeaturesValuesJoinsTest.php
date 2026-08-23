<?php

namespace Entities;

use Okay\Core\Modules\ModulesEntitiesFilters;
use Okay\Entities\FeaturesValuesEntity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * find() приєднує pf, f і p лише тим фільтрам, які їх читають. Забутий джойн не
 * падає: Database::query() ковтає виняток у лог, тож блок фільтра просто зникає
 * зі сторінки. Саме тому потрібна статична перевірка, а не прохід руками.
 */
class FeaturesValuesJoinsTest extends TestCase
{
    /** @return array<string, array{array<string, mixed>, bool, bool, bool}> */
    public static function filterProvider(): array
    {
        return [
            'порожній фільтр'      => [[], false, false, false],
            'visible'              => [['visible' => 1], true, false, true],
            'in_stock'             => [['in_stock' => true], true, false, true],
            'price'                => [['price' => [1, 2]], false, false, false],
            'features'             => [['features' => [1 => [2]]], true, false, true],
            'product_id'           => [['product_id' => 1], true, false, false],
            'brand_id'             => [['brand_id' => 1], true, false, false],
            'have_products'        => [['have_products_in_categories' => 1], true, false, false],
            'other_filter'         => [['other_filter' => ['featured']], true, false, false],
            'category_id'          => [['category_id' => 1], false, true, false],
            'category_id+visible'  => [['category_id' => 1, 'visible' => 1], true, true, true],
            'keyword'              => [['keyword' => 'x'], false, false, false],
            'product_keyword'      => [['product_keyword' => 'x'], false, false, false],
            'brand'                => [['brand' => 'x'], false, false, false],
            'selected_features'    => [['selected_features' => [1 => ['a']]], false, false, false],
            'колонки сутності'     => [['id' => 1, 'feature_id' => 2], false, false, false],
        ];
    }

    #[DataProvider('filterProvider')]
    public function testResolvesJoinsPerFilter(array $filter, bool $pf, bool $f, bool $p): void
    {
        self::assertSame([$pf, $f, $p], self::resolve($filter, []));
    }

    public function testUnknownModuleFilterKeepsEveryJoin(): void
    {
        self::assertSame([true, true, true], self::resolve(['vendor_stock' => 1], ['vendor_stock']));
    }

    /**
     * Резолвер тримає pf разом із p, бо джойн товарів іде через pf.product_id.
     * Перепишуть умову джойна — перевірити треба й цю залежність.
     */
    public function testProductsJoinStillDependsOnProductValues(): void
    {
        $source = file_get_contents((new ReflectionClass(FeaturesValuesEntity::class))->getFileName());

        self::assertMatchesRegularExpression(
            "/'__products AS p',\s*'[^']*\bpf\./",
            $source,
            'джойн p більше не спирається на pf — залежність у resolveFindJoins застаріла'
        );
    }

    /**
     * Головна страховка: фільтр, який згадує аліас, мусить його й замовити.
     * Інакше новий filter__* мовчки лишиться без джойна.
     */
    /**
     * Сканер мовчки віддав би порожній список, якби розбір файлу зламався, —
     * і весь блок перевірок став би зеленим ні на чому.
     */
    public function testScannerReachesFilterBodiesAndTheirHelpers(): void
    {
        $cases = self::aliasUsageProvider();

        self::assertNotEmpty($cases, 'не знайдено жодного filter__* — розбір файлу зламався');
        self::assertArrayHasKey('other_filter → pf', $cases, 'сканер не дістає SQL із помічника фільтра');
    }

    #[DataProvider('aliasUsageProvider')]
    public function testFilterAskingForAliasGetsIt(string $filterName, string $alias): void
    {
        [$pf, $f, $p] = self::resolve([$filterName => 1], []);
        $joined = ['pf' => $pf, 'f' => $f, 'p' => $p];

        self::assertTrue(
            $joined[$alias],
            sprintf('filter__%s читає %s., але find() цей джойн не ставить', $filterName, $alias)
        );
    }

    /** @return array<string, array{string, string}> */
    public static function aliasUsageProvider(): array
    {
        $methods = self::splitMethods(
            file_get_contents((new ReflectionClass(FeaturesValuesEntity::class))->getFileName())
        );

        $cases = [];
        foreach ($methods as $name => $body) {
            if (!str_starts_with($name, 'filter__')) {
                continue;
            }

            $filterName = substr($name, strlen('filter__'));
            foreach (['pf', 'f', 'p'] as $alias) {
                if (preg_match('/(?<![\w.])' . $alias . '\./', self::inlineCallees($body, $methods))) {
                    $cases[$filterName . ' → ' . $alias] = [$filterName, $alias];
                }
            }
        }

        return $cases;
    }

    /**
     * filter__other_filter сам SQL не пише — його складає executeOtherFilter().
     * Без підстановки тіл викликаних методів сканер бачив би порожню заглушку.
     */
    private static function inlineCallees(string $body, array $methods): string
    {
        foreach ($methods as $name => $calleeBody) {
            if (str_contains($body, '$this->' . $name . '(')) {
                $body .= "\n" . $calleeBody;
            }
        }

        return $body;
    }

    /** @return array<string, string> */
    private static function splitMethods(string $source): array
    {
        $parts = preg_split(
            '/\n\s*(?:protected|private|public)\s+(?:static\s+)?function\s+(\w+)/',
            $source,
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        );

        $bodies = [];
        for ($i = 1; $i < count($parts); $i += 2) {
            // Тіло обривається на наступному оголошенні — далі вже чужий код
            $bodies[$parts[$i]] = preg_split('/\n\s*(?:protected|private|public)\s+(?:static\s+)?function\s/', $parts[$i + 1])[0];
        }

        return $bodies;
    }

    /**
     * @param array<string, mixed> $filter
     * @param string[] $moduleFilterNames
     * @return bool[]
     */
    private static function resolve(array $filter, array $moduleFilterNames): array
    {
        $class = new ReflectionClass(FeaturesValuesEntity::class);
        $entity = $class->newInstanceWithoutConstructor();

        $class->getParentClass()->getProperty('modulesFilters')
            ->setValue($entity, self::modulesFilters($moduleFilterNames));

        return $class->getMethod('resolveFindJoins')->invoke($entity, $filter);
    }

    private static function modulesFilters(array $names): ModulesEntitiesFilters
    {
        return new class ($names) extends ModulesEntitiesFilters {
            /** @param string[] $names */
            public function __construct(private array $names)
            {
            }

            public function hasFilter($entityClassName, $filterName)
            {
                return in_array($filterName, $this->names, true);
            }
        };
    }
}
