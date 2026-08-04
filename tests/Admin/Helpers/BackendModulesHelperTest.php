<?php

namespace Admin\Helpers;

use Okay\Admin\Helpers\BackendModulesHelper;
use Okay\Core\Config;
use Okay\Core\Settings;
use PHPUnit\Framework\TestCase;

/**
 * updateModulesAccessExpiresCache() стирало кеш, коли email_for_module
 * порожній, і перевірка кешу одразу після цього ніколи не спрацьовувала.
 * Наслідок - блокуючий запит на маркетплейс на кожному запиті адмінки
 * (~0.27 с) плюс два UPDATE в ok_settings.
 */
class BackendModulesHelperTest extends TestCase
{
    public function testDoesNotCallMarketplaceWithoutEmail(): void
    {
        $helper = $this->makeHelper(['email_for_module' => '']);

        $helper->updateModulesAccessExpiresCache();

        $this->assertCount(
            0,
            $helper->requestedUrls,
            'Без email_for_module запитувати маркетплейс нема про що.'
        );
    }

    public function testDoesNotCallMarketplaceWhenCacheIsFresh(): void
    {
        $helper = $this->makeHelper([
            'email_for_module'          => 'shop@example.com',
            'modules_access_expires'    => ['Vendor/Module' => (object)['expires' => '2030-01-01']],
            'modules_access_check_date' => date('Y-m-d'),
        ]);

        $helper->updateModulesAccessExpiresCache();

        $this->assertCount(0, $helper->requestedUrls, 'Кеш за сьогодні - запит зайвий.');
    }

    public function testCallsMarketplaceOnceWhenCacheIsStale(): void
    {
        $helper = $this->makeHelper([
            'email_for_module'          => 'shop@example.com',
            'modules_access_expires'    => ['Vendor/Module' => (object)['expires' => '2030-01-01']],
            'modules_access_check_date' => date('Y-m-d', strtotime('-1 day')),
        ]);

        $helper->updateModulesAccessExpiresCache();

        $this->assertCount(1, $helper->requestedUrls, 'Прострочений кеш оновлюється рівно одним запитом.');
        $this->assertStringContainsString(
            'v2/modules/access/expires/email',
            $helper->requestedUrls[0]
        );
    }

    /**
     * Фонове оновлення трапляється всередині чужого запиту, тож не має права
     * тримати воркера стільки ж, скільки виклик, який адмін ініціював сам.
     */
    public function testBackgroundRefreshUsesShortTimeouts(): void
    {
        $helper = $this->makeHelper([
            'email_for_module'          => 'shop@example.com',
            'modules_access_check_date' => date('Y-m-d', strtotime('-1 day')),
        ]);

        $helper->updateModulesAccessExpiresCache();

        [$connectTimeout, $timeout] = $helper->requestedTimeouts[0];
        $this->assertLessThan(3, $connectTimeout, 'CONNECTTIMEOUT фонового оновлення має бути меншим за типовий.');
        $this->assertLessThan(10, $timeout, 'TIMEOUT фонового оновлення має бути меншим за типовий.');
    }

    /**
     * А виклики, які ініціює адмін (пошук по маркетплейсу, список версій),
     * навпаки, мають зберегти щедрі таймаути - людина натиснула кнопку.
     */
    public function testAdminInitiatedRequestsKeepGenerousTimeouts(): void
    {
        $helper = $this->makeHelper(['email_for_module' => 'shop@example.com']);

        $helper->findModules('seo');

        $this->assertSame([3, 10], $helper->requestedTimeouts[0]);
    }

    private function makeHelper(array $settingsValues): BackendModulesHelperSpy
    {
        $config = $this->createStub(Config::class);
        $config->method('get')->willReturnCallback(
            static fn ($param) => $param === 'marketplace_url' ? 'https://example.test/' : null
        );

        $values = $settingsValues;

        $settings = $this->createStub(Settings::class);
        $settings->method('get')->willReturnCallback(
            static function ($param) use (&$values) {
                return $values[$param] ?? null;
            }
        );
        $settings->method('set')->willReturnCallback(
            static function ($param, $value) use (&$values) {
                $values[$param] = $value;
            }
        );

        return new BackendModulesHelperSpy($config, $settings);
    }
}

/**
 * Хелпер із перекритим request(): справжній curl у тесті не потрібен,
 * важливо лише чи дійшло до нього виконання.
 */
class BackendModulesHelperSpy extends BackendModulesHelper
{
    /** @var string[] */
    public array $requestedUrls = [];

    /** @var array<int, array{0: int, 1: int}> */
    public array $requestedTimeouts = [];

    public function request($url, int $connectTimeout = 3, int $timeout = 10)
    {
        $this->requestedUrls[] = $url;
        $this->requestedTimeouts[] = [$connectTimeout, $timeout];
        return false;
    }
}
