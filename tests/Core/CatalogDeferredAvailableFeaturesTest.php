<?php

namespace Core;

use Okay\Core\Settings;
use Okay\Helpers\CatalogHelper;
use Okay\Helpers\MetaRobotsHelper;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Можливі значення свойств при відкладеному завантаженні фільтра. Раніше ця
 * логіка жила копією в чотирьох контролерах (категорія, бренд, усі товари,
 * бренди), і правка фільтра мала чотири шанси розійтись.
 */
class CatalogDeferredAvailableFeaturesTest extends TestCase
{
    public function testSelectedValueMissingFromBaseValuesIsStillAvailable(): void
    {
        // Базові значення при missing_products=hide звужені по in_stock: вибраного
        // значення з товарами не в наявності серед них немає.
        [$features, $available] = $this->deferred(
            ['features' => [1 => [11 => 'x']]],
            base: [],
            selected: [self::value(11, 1)]
        );

        self::assertArrayHasKey(1, $features);
        self::assertArrayHasKey(11, $features[1]->features_values);
        self::assertSame($features, $available, 'MetaRobotsHelper має дістати той самий набір');
    }

    public function testFeatureWithoutValuesIsRemovedUnlessSelected(): void
    {
        [$features] = $this->deferred(
            ['features' => [2 => [21 => 'y']]],
            base: [3 => [self::value(31, 3)]],
            selected: []
        );

        self::assertArrayNotHasKey(1, $features, 'свойство без значень лишилось');
        self::assertArrayHasKey(2, $features, 'вибране свойство прибрано — getCatalogRobots() кине виняток');
        self::assertArrayHasKey(3, $features);
    }

    public function testBaseValuesAreAttachedOnlyToCatalogFeatures(): void
    {
        [$features] = $this->deferred(
            [],
            base: [1 => [self::value(12, 1)], 99 => [self::value(991, 99)]],
            selected: []
        );

        self::assertSame([1], array_keys($features));
        self::assertSame([12], array_keys($features[1]->features_values));
    }

    /**
     * @return array{0: array<int, object>, 1: array<int, object>|null}
     */
    private function deferred(array $productsFilter, array $base, array $selected): array
    {
        $helper = $this->getMockBuilder(CatalogHelper::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getBaseFeaturesValues', 'getSelectedFeaturesValues'])
            ->getMock();
        $helper->expects($this->once())->method('getBaseFeaturesValues')->willReturn($base);
        $helper->expects($this->once())->method('getSelectedFeaturesValues')->willReturn($selected);

        $available = null;
        $robots = $this->createStub(MetaRobotsHelper::class);
        $robots->method('setAvailableFeatures')->willReturnCallback(function ($features) use (&$available, &$robots) {
            $available = $features;
            return $robots;
        });

        foreach (['settings' => $this->createStub(Settings::class), 'metaRobotsHelper' => $robots] as $property => $value) {
            (new ReflectionProperty(CatalogHelper::class, $property))->setValue($helper, $value);
        }

        $catalogFeatures = [
            1 => (object) ['id' => 1],
            2 => (object) ['id' => 2],
            3 => (object) ['id' => 3],
        ];

        return [$helper->setDeferredAvailableFeatures($productsFilter, $catalogFeatures), $available];
    }

    private static function value(int $id, int $featureId): object
    {
        return (object) ['id' => $id, 'feature_id' => $featureId];
    }
}
