<?php

namespace Modules\OkayCMS\NovaposhtaCost;

use Okay\Modules\OkayCMS\NovaposhtaCost\Helpers\NPApiHelper;
use PHPUnit\Framework\TestCase;

/**
 * API НП відповідає "Page is invalid format" на числовий Page у getCities,
 * через що кеш міст переставав оновлюватись.
 */
class NPApiHelperTest extends TestCase
{
    public function testCitiesRequestSendsPaginationAsStrings(): void
    {
        $properties = $this->capturedMethodProperties(
            static fn (NPApiHelper $api) => $api->getCities(3, 1000)
        );

        $this->assertSame('3', $properties['Page']);
        $this->assertSame('1000', $properties['Limit']);
    }

    public function testWarehousesRequestSendsPaginationAsStrings(): void
    {
        $properties = $this->capturedMethodProperties(
            static fn (NPApiHelper $api) => $api->getWarehouses('type-ref', 3, 1000)
        );

        $this->assertSame('3', $properties['Page']);
        $this->assertSame('1000', $properties['Limit']);
        $this->assertSame('type-ref', $properties['TypeOfWarehouseRef']);
    }

    /**
     * Ретраї тримають php-fpm воркер до півтори хвилини (3 × TIMEOUT 30 плюс
     * паузи), тож вони дозволені лише там, де на відповідь ніхто не чекає.
     * Клієнтські шляхи — автокомпліт адреси (`NovaposhtaCostSearchController`,
     * minChars 1, запит на кожну літеру) і розрахунок доставки
     * (`NPCalcHelper`) — кличуть request() напряму й лишаються на одній
     * спробі. Ретрай на «To many requests» звідти ще й підсилював би саме той
     * ліміт, у який упирався.
     */
    public function testBackgroundCallsAskForRetriesAndNothingElseDoes(): void
    {
        $this->assertSame(3, $this->capturedAttempts(
            static fn (NPApiHelper $api) => $api->getCities(1, 1000)
        ));
        $this->assertSame(3, $this->capturedAttempts(
            static fn (NPApiHelper $api) => $api->getWarehouses('type-ref', 1, 1000)
        ));
        $this->assertSame(3, $this->capturedAttempts(
            static fn (NPApiHelper $api) => $api->getWarehouseTypes()
        ));

        // Сторінка налаштувань чекає відповіді так само, як покупець.
        $this->assertSame(1, $this->capturedAttempts(
            static fn (NPApiHelper $api) => $api->checkApiKey()
        ));
    }

    /** За замовчуванням ретраїв немає: саме так request() бачать контролери. */
    public function testRequestDefaultsToASingleAttempt(): void
    {
        $parameters = (new \ReflectionMethod(NPApiHelper::class, 'request'))->getParameters();

        $this->assertSame('maxAttempts', $parameters[2]->getName());
        $this->assertSame(1, $parameters[2]->getDefaultValue());
    }

    private function capturedAttempts(callable $call): int
    {
        $captured = 1;

        $api = $this->getMockBuilder(NPApiHelper::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['request'])
            ->getMock();
        $api->expects($this->once())
            ->method('request')
            ->willReturnCallback(function (array $request, bool $useKey = true, int $attempts = 1) use (&$captured) {
                $captured = $attempts;
                return false;
            });

        $call($api);

        return $captured;
    }

    private function capturedMethodProperties(callable $call): array
    {
        $captured = [];

        $api = $this->getMockBuilder(NPApiHelper::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['request'])
            ->getMock();
        $api->expects($this->once())
            ->method('request')
            ->willReturnCallback(function (array $request) use (&$captured) {
                $captured = $request['methodProperties'];
                return false;
            });

        $call($api);

        return $captured;
    }
}
