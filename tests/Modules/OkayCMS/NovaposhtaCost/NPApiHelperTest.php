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
