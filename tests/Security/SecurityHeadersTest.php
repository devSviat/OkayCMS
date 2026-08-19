<?php

namespace Security;

use Okay\Core\Response;
use Okay\Core\Security\SecurityHeaders;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class SecurityHeadersTest extends TestCase
{
    public function testDefaultsCoverFramingSniffingAndReferrer()
    {
        $headers = SecurityHeaders::defaults();

        $this->assertContains('X-Frame-Options: SAMEORIGIN', $headers);
        $this->assertContains('X-Content-Type-Options: nosniff', $headers);
        $this->assertContains('Referrer-Policy: strict-origin-when-cross-origin', $headers);
    }

    public function testDefaultsAreHeaderLines()
    {
        foreach (SecurityHeaders::defaults() as $header) {
            $this->assertIsString($header);
            $this->assertStringContainsString(': ', $header);
            $this->assertStringNotContainsString("\n", $header);
            $this->assertStringNotContainsString("\r", $header);
        }
    }

    /**
     * Ім'я рушія в банері лишається свідомо, а от версія — ні: саме вона
     * перетворювала заголовок на готову ціль для сканерів.
     */
    public function testResponseNoLongerAdvertisesTheVersion()
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/Okay/Core/Response.php');
        $this->assertIsString($source);

        $this->assertStringNotContainsString("'X-Powered-CMS: OkayCMS ' . \$version", $source);
        $this->assertStringContainsString("'X-Powered-CMS: OkayCMS'", $source);
        $this->assertStringContainsString('SecurityHeaders::defaults()', $source);
    }

    /**
     * redirectTo() завершується exit'ом повз sendHeaders(). Найважливіший тут
     * Referrer-Policy: він вирішує, який Referer побачить ціль переходу.
     */
    public function testRedirectsCarryTheSameDefaults()
    {
        $method = new ReflectionMethod(Response::class, 'redirectTo');
        $source = file(dirname(__DIR__, 2) . '/Okay/Core/Response.php');
        $body = implode('', array_slice(
            $source,
            $method->getStartLine() - 1,
            $method->getEndLine() - $method->getStartLine() + 1
        ));

        $this->assertStringContainsString('SecurityHeaders::defaults()', $body);
        $this->assertStringContainsString('Location: ', $body);
    }

    /**
     * Заголовки ставить Okay\Core\Response, тож точка входу, яка віддає
     * відповідь повз нього, лишається без них. Раніше такими були процедурні
     * входи файлового менеджера і їм доводилось слати заголовки самим.
     */
    #[DataProvider('entryPointProvider')]
    public function testEveryEntryPointAnswersThroughResponse($file)
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $file);
        $this->assertIsString($source, $file);

        $this->assertStringContainsString('Okay\Core\Response', $source, $file);
    }

    public static function entryPointProvider()
    {
        return [
            'storefront'   => ['index.php'],
            'backend'      => ['backend/index.php'],
            'ajax config'  => ['backend/ajax/configure.php'],
            'admintooltip' => ['backend/design/js/admintooltip/admintooltip.php'],
            'backend files'=> ['backend/files/index.php'],
        ];
    }
}
