<?php

namespace Security;

use Okay\Core\Security\RequestOrigin;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RequestOriginTest extends TestCase
{
    private const SITE = 'https://shop.example';

    #[DataProvider('acceptedProvider')]
    public function testRequestsFromTheShopItselfPass($origin, $referer, $site = self::SITE)
    {
        $this->assertTrue(RequestOrigin::matchesSite($origin, $referer, $site));
    }

    public static function acceptedProvider()
    {
        return [
            'origin'                 => ['https://shop.example', null],
            'origin with slash'      => ['https://shop.example/', null],
            'origin, other case'     => ['https://SHOP.EXAMPLE', null],
            'referer when no origin' => [null, 'https://shop.example/user/login'],
            'referer when origin is empty string' => ['', 'https://shop.example/user'],
            // Порт за замовчуванням в origin не пишеться, але дійти може.
            'explicit default port'  => ['https://shop.example:443', null],
            // Нестандартний порт - той самий origin, якщо збігається з сайтом.
            'same custom port'       => ['http://shop.example:8080', null, 'http://shop.example:8080'],
        ];
    }

    #[DataProvider('rejectedProvider')]
    public function testEverythingElseIsRejected($origin, $referer, $site = self::SITE)
    {
        $this->assertFalse(RequestOrigin::matchesSite($origin, $referer, $site));
    }

    public static function rejectedProvider()
    {
        return [
            'foreign origin'        => ['https://evil.example', null],
            'foreign referer'       => [null, 'https://evil.example/attack.html'],
            // Схема - частина origin: на http зловмисник у тій же мережі
            // підставляє власну сторінку.
            'same host, http'       => ['http://shop.example', null],
            'other port'            => ['https://shop.example:8443', null],
            // Піддомен - окремий origin.
            'subdomain'             => ['https://a.shop.example', null],
            // Хост-префікс: evil.example не є shop.example.
            'host prefix'           => ['https://shop.example.evil.test', null],
            'nothing at all'        => [null, null],
            'both empty'            => ['', ''],
            // Пісочниця в iframe шле саме рядок "null". Запасний Referer тут
            // не рятує: origin уже сказав, що сторінка не наша.
            'literal null origin'   => ['null', 'https://shop.example/user/login'],
            'literal NULL origin'   => ['NULL', 'https://shop.example/user/login'],
            // Origin виграє в Referer, а не навпаки.
            'foreign origin beats own referer' => ['https://evil.example', 'https://shop.example/'],
            'garbage'               => ['not a url', null],
            'scheme without host'   => ['https://', null],
            'non-http scheme'       => ['ftp://shop.example', null],
            'javascript scheme'     => ['javascript:alert(1)', null],
            'site url unparsable'   => ['https://shop.example', null, 'not a url'],
            // Класична маскувалка: справжній хост тут evil.example.
            'credentials mask the host' => ['https://shop.example@evil.example', null],
            // Браузер облікових даних в Origin не шле; раз їх не буває в
            // чесному заголовку, не приймаємо навіть зі своїм хостом.
            'credentials, own host'     => ['https://user@shop.example', null],
            'credentials with password' => ['https://user:pass@shop.example', null],
        ];
    }

    /**
     * Referer несе шлях і query, origin - ні. Порівнюється саме origin, інакше
     * власна сторінка з параметрами не проходила б.
     */
    public function testRefererPathIsIgnored()
    {
        $this->assertTrue(RequestOrigin::matchesSite(
            null,
            'https://shop.example/user/login?from=header#anchor',
            self::SITE
        ));
    }
}
