<?php

namespace Security;

use Okay\Core\Security\SessionNames;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Кука сесії має SameSite=Lax, тож міжсайтовий POST приїздить без неї.
 * Нова кука у відповідь затирала сесію відвідувача, і той розлогінювався.
 */
class CrossSitePostSessionTest extends TestCase
{
    private $server;
    private $cookie;

    protected function setUp(): void
    {
        $this->server = $_SERVER;
        $this->cookie = $_COOKIE;
        $_SERVER['HTTP_HOST'] = 'shop.example';
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $_SERVER['SERVER_PORT'] = '80';
        unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO'], $_SERVER['HTTP_X_FORWARDED_SSL']);
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        $_COOKIE = $this->cookie;
    }

    #[DataProvider('requestProvider')]
    public function testCookieIsWithheldOnlyFromCrossSitePosts($method, $origin, $hasCookie, $expected, $why): void
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        if ($origin === null) {
            unset($_SERVER['HTTP_ORIGIN']);
        } else {
            $_SERVER['HTTP_ORIGIN'] = $origin;
        }

        $_COOKIE = $hasCookie ? [SessionNames::FRONTEND => str_repeat('a', 32)] : [];

        $method = new ReflectionMethod(SessionNames::class, 'isCrossSitePostWithoutSession');

        $this->assertSame($expected, $method->invoke(null, SessionNames::FRONTEND), $why);
    }

    public static function requestProvider()
    {
        return [
            'чужий POST без куки' => [
                'POST', 'http://evil.example', false, true,
                'саме цей запит затирав сесію відвідувача',
            ],
            'чужий POST, кука вже є' => [
                'POST', 'http://evil.example', true, false,
                'кука є - затирати нічого, звичайний шлях',
            ],
            'власна форма' => [
                'POST', 'http://shop.example', false, false,
                'перший POST відвідувача без куки має отримати сесію',
            ],
            'власна форма, інший регістр хоста' => [
                'POST', 'http://SHOP.EXAMPLE', false, false,
                'хост регістронезалежний',
            ],
            'серверний колбек платіжної системи' => [
                'POST', null, false, false,
                'колбеки Origin не шлють, а сесія їм не шкодить',
            ],
            'GET з чужого сайту' => [
                'GET', 'http://evil.example', false, false,
                'на GET-навігації браузер Origin не шле, вгадувати нічого',
            ],
            'звичайний GET' => [
                'GET', null, false, false,
                'типовий перший запит відвідувача',
            ],
            'чужа схема на тому ж хості' => [
                'POST', 'https://shop.example', false, true,
                'схема - частина origin',
            ],
            'чужий порт' => [
                'POST', 'http://shop.example:8443', false, true,
                'порт - частина origin',
            ],
            'суфікс хоста' => [
                'POST', 'http://shop.example.evil.test', false, true,
                'shop.example.evil.test не є shop.example',
            ],
        ];
    }
}
