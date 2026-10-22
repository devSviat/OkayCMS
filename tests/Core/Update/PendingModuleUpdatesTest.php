<?php

namespace Core\Update;

use Okay\Core\Modules\DTO\ModuleParamsDTO;
use Okay\Core\Modules\Module;
use Okay\Core\Modules\VersionControl;
use Okay\Core\Update\PendingModuleUpdates;
use PHPUnit\Framework\TestCase;

/**
 * Сторінка оновлення ядра перелічує модулі, чиї update_x_y_z() ще не виконані.
 * Без цього незапущена міграція модуля після релізу ніде не видна.
 */
class PendingModuleUpdatesTest extends TestCase
{
    public function testOnlyModulesBehindTheirModuleJsonAreListed(): void
    {
        $pending = PendingModuleUpdates::collect([
            self::row('OkayCMS', 'GoogleMerchant', '1.2.0'),
            self::row('OkayCMS', 'NovaposhtaCost', '1.3.0'),
            self::row('Sviat', 'Ahead', '2.1.0'),
        ], $this->moduleCore([
            'OkayCMS/GoogleMerchant' => ['1.3.0', true],
            'OkayCMS/NovaposhtaCost' => ['1.3.0', true],
            'Sviat/Ahead' => ['2.0.0', true],
        ]));

        self::assertSame([[
            'vendor' => 'OkayCMS',
            'module_name' => 'GoogleMerchant',
            'installed' => '1.2.0',
            'available' => '1.3.0',
        ]], $pending);
    }

    /** Без ліцензії сторінка «Модулі» кнопки не показує, тож і тут нема куди вести. */
    public function testUnlicensedModuleIsNotListed(): void
    {
        self::assertSame([], PendingModuleUpdates::collect(
            [self::row('Vendor', 'Paid', '1.0.0')],
            $this->moduleCore(['Vendor/Paid' => ['1.1.0', false]])
        ));
    }

    /** ok_modules.version буває NULL: модуль стоїть, а версія не записана. */
    public function testModuleWithoutRecordedVersionIsListed(): void
    {
        $pending = PendingModuleUpdates::collect(
            [self::row('OkayCMS', 'Legacy', null)],
            $this->moduleCore(['OkayCMS/Legacy' => ['1.0.1', true]])
        );

        self::assertSame('', $pending[0]['installed']);
        self::assertSame('1.0.1', $pending[0]['available']);
    }

    /** @param array<string, array{0: string, 1: bool}> $json vendor/module => [версія, ліцензія] */
    private function moduleCore(array $json): Module
    {
        $module = $this->createStub(Module::class);
        $module->method('getVersionControl')->willReturn(new VersionControl());
        $module->method('getModuleParams')->willReturnCallback(function ($vendor, $name) use ($json) {
            [$version, $licensed] = $json["$vendor/$name"];
            $params = new ModuleParamsDTO();
            $params->setVersion($version);
            $params->setIsLicensed($licensed);

            return $params;
        });

        return $module;
    }

    private static function row(string $vendor, string $name, ?string $version): object
    {
        return (object) ['vendor' => $vendor, 'module_name' => $name, 'version' => $version];
    }
}
