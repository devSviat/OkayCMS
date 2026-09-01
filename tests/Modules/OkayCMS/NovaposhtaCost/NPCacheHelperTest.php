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
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * removeRedundant() видаляє все, чий updated_at старіший за час старту оновлення.
 * Якщо API НП недоступне, крон не імпортує нічого - і без цих перевірок
 * підчищання вимітало весь робочий кеш міст та відділень.
 */
class NPCacheHelperTest extends TestCase
{
    private const CITIES_PAGES = 9;

    /**
     * НП не завжди фільтрує totalCount за TypeOfWarehouseRef: для типу
     * «Поштове відділення з обмеженням» він віддає ті самі 13.5 тис., що й для
     * звичайного «Поштове(ий)», але жодного рядка цього типу в data немає.
     * Перевірено живим викликом на бойовому ключі.
     *
     * Без межі на порожній сторінці кожен добовий прогін витрачав на такий тип
     * 14 запитів, з яких не імпортується нічого. Свій ліміт НП віддає кодом 200
     * з errors: ["To many requests"], тож марні запити не безкоштовні.
     *
     * Правило арифметичне, тож і перевіряється арифметично.
     */
    #[DataProvider('pagesLeftProvider')]
    public function testEmptyPageIsTheLastOne(int $totalCount, int $page, bool $empty, int $expected): void
    {
        // Правило не залежить від жодної залежності хелпера, тож і екземпляр
        // тут без конструктора — без моків, яким нема чого очікувати.
        $helper = (new \ReflectionClass(NPCacheHelper::class))->newInstanceWithoutConstructor();

        $this->assertSame(
            $expected,
            (new \ReflectionMethod(NPCacheHelper::class, 'pagesLeft'))
                ->invoke($helper, $totalCount, 1000, $page, $empty)
        );
    }

    public static function pagesLeftProvider(): array
    {
        return [
            'сторінка з даними — межа з totalCount' => [13514, 1, false, 14],
            'порожня перша — далі не йдемо'         => [13514, 1, true, 1],
            'порожня посеред пагінації'             => [13514, 5, true, 5],
            'порожня після останньої сторінки'      => [13514, 14, true, 14],
            'відповідь без totalCount'              => [0, 1, true, 0],
            'рівний поділ'                          => [2000, 1, false, 2],
        ];
    }

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

    #[DataProvider('startUpdateTimeProvider')]
    public function testStartUpdateTimeIsDroppedOnceItIsOlderThanHalfAnHour(
        int $minutesAgo,
        bool $expectedUsable
    ): void {
        $startTime = date('Y-m-d H:i:s', time() - $minutesAgo * 60);

        $settings = $this->createStub(Settings::class);
        // with() без expects() у PHPUnit 14 зникне — умова переїхала в колбек.
        $settings->method('get')->willReturnCallback(
            static fn (string $name): ?string => $name === 'np_start_update_datetime' ? $startTime : null
        );

        $helper = new NPCacheHelper(
            $this->createStub(NPApiHelper::class),
            $this->createStub(EntityFactory::class),
            $this->createStub(Languages::class),
            $settings
        );

        $this->assertSame(
            $expectedUsable ? $startTime : null,
            $helper->getStartUpdateTime()
        );
    }

    public static function startUpdateTimeProvider(): array
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
