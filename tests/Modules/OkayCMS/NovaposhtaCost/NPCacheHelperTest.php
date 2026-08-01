<?php

namespace Modules\OkayCMS\NovaposhtaCost;

use Okay\Core\EntityFactory;
use Okay\Core\Languages;
use Okay\Core\Settings;
use Okay\Modules\OkayCMS\NovaposhtaCost\DTO\NPWarehouseTypeDTO;
use Okay\Modules\OkayCMS\NovaposhtaCost\Helpers\NPApiHelper;
use Okay\Modules\OkayCMS\NovaposhtaCost\Helpers\NPCacheHelper;
use Okay\Modules\OkayCMS\NovaposhtaCost\Init\Init;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * removeRedundant() видаляє все, чий updated_at старіший за час старту оновлення.
 * Якщо API НП недоступне, крон не імпортує нічого - і без цих перевірок
 * підчищання вимітало весь робочий кеш міст та відділень.
 */
class NPCacheHelperTest extends TestCase
{
    private const CITIES_PAGES = 9;

    public function testCitiesCacheIsNotPrunedWhenApiCallFails(): void
    {
        $helper = $this->helperMock(['updateCitiesCache', 'removeRedundant']);
        $helper->method('updateCitiesCache')->willReturn(null);
        $helper->expects($this->never())->method('removeRedundant');

        $helper->cronUpdateCitiesCache();
    }

    public function testCitiesCacheIsNotPrunedWhenResponseCarriesNoTotalCount(): void
    {
        $helper = $this->helperMock(['updateCitiesCache', 'removeRedundant']);
        $helper->method('updateCitiesCache')->willReturn(0);
        $helper->expects($this->never())->method('removeRedundant');

        $helper->cronUpdateCitiesCache();
    }

    public function testCitiesCacheIsPrunedAfterFullUpdate(): void
    {
        $helper = $this->helperMock(['updateCitiesCache', 'removeRedundant']);
        $helper->expects($this->exactly(self::CITIES_PAGES))
            ->method('updateCitiesCache')
            ->willReturn(self::CITIES_PAGES);
        $helper->expects($this->once())
            ->method('removeRedundant')
            ->with(Init::UPDATE_TYPE_CITIES);

        $helper->cronUpdateCitiesCache();
    }

    public function testOnlyWarehouseTypesThatUpdatedArePruned(): void
    {
        $helper = $this->helperMock([
            'updateWarehousesCache',
            'removeRedundant',
            'getUpdatedWarehousesTypes',
        ]);
        $helper->method('getUpdatedWarehousesTypes')->willReturn([
            new NPWarehouseTypeDTO('Відділення', 'Отделение', 'ref-failed'),
            new NPWarehouseTypeDTO('Поштомат', 'Почтомат', 'ref-ok'),
        ]);
        $helper->method('updateWarehousesCache')->willReturnCallback(
            static fn (string $type): ?int => $type === 'ref-ok' ? 1 : null
        );
        $helper->expects($this->once())
            ->method('removeRedundant')
            ->with(Init::UPDATE_TYPE_WAREHOUSES, 'ref-ok');

        $helper->cronUpdateWarehousesCache();
    }

    /**
     * @dataProvider startUpdateTimeProvider
     */
    public function testStartUpdateTimeIsDroppedOnceItIsOlderThanHalfAnHour(
        int $minutesAgo,
        bool $expectedUsable
    ): void {
        $startTime = date('Y-m-d H:i:s', time() - $minutesAgo * 60);

        $settings = $this->createMock(Settings::class);
        $settings->method('get')->with('np_start_update_datetime')->willReturn($startTime);

        $helper = new NPCacheHelper(
            $this->createMock(NPApiHelper::class),
            $this->createMock(EntityFactory::class),
            $this->createMock(Languages::class),
            $settings
        );

        $this->assertSame(
            $expectedUsable ? $startTime : null,
            $helper->getStartUpdateTime()
        );
    }

    public function startUpdateTimeProvider(): array
    {
        return [
            'щойно'          => [10, true],
            'майже година'   => [55, false],
            // Хвилинна складова інтервалу тут 10 і 5 - на них перевірка ловилась.
            'три з гаком'    => [190, false],
            'доба'           => [1445, false],
        ];
    }

    /**
     * @return NPCacheHelper&MockObject
     */
    private function helperMock(array $methods): MockObject
    {
        $methods[] = 'rememberStartUpdateTime';

        return $this->getMockBuilder(NPCacheHelper::class)
            ->disableOriginalConstructor()
            ->onlyMethods($methods)
            ->getMock();
    }
}
