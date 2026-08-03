<?php

namespace Security;

use Okay\Core\Request;
use Okay\Core\Security\AdminCsrfToken;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * checkSession() виходив з припущення "мутація завжди має непорожній $_POST":
 * при empty($_POST) він повертав true, не перевіривши нічого. Тобто POST без
 * полів і будь-який PUT/PATCH/DELETE проходили повз CSRF-гард.
 */
class AdminCsrfMethodGuardTest extends TestCase
{
    private $request;

    protected function setUp(): void
    {
        parent::setUp();

        $_SESSION = [];
        $_POST = [];
        $this->request = new Request();
    }

    protected function tearDown(): void
    {
        unset($_SERVER['REQUEST_METHOD']);
        $_POST = [];
        $_SESSION = [];

        parent::tearDown();
    }

    #[DataProvider('unsafeMethodProvider')]
    public function testUnsafeMethodWithoutATokenIsRejected($method)
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        AdminCsrfToken::get();

        $this->assertFalse($this->request->checkSession(), $method);
    }

    #[DataProvider('unsafeMethodProvider')]
    public function testEmptyBodyDoesNotSkipTheGuard($method)
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_POST = [];
        AdminCsrfToken::get();

        $this->assertFalse($this->request->checkSession(), $method);
    }

    public static function unsafeMethodProvider()
    {
        return [
            'POST'   => ['POST'],
            'PUT'    => ['PUT'],
            'PATCH'  => ['PATCH'],
            'DELETE' => ['DELETE'],
        ];
    }

    #[DataProvider('safeMethodProvider')]
    public function testSafeMethodsAreNotGuarded($method)
    {
        $_SERVER['REQUEST_METHOD'] = $method;

        $this->assertTrue($this->request->checkSession(), $method);
    }

    public static function safeMethodProvider()
    {
        return [
            'GET'     => ['GET'],
            'HEAD'    => ['HEAD'],
            'OPTIONS' => ['OPTIONS'],
        ];
    }

    public function testValidTokenPasses()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['session_id' => AdminCsrfToken::get(), 'name' => 'значення'];

        $this->assertTrue($this->request->checkSession());
        $this->assertSame('значення', $_POST['name']);
    }

    /**
     * Після відмови нижчий код не має побачити жодного поля з відхиленого
     * запиту.
     */
    public function testRejectedRequestLosesItsBody()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['session_id' => str_repeat('a', 64), 'name' => 'значення'];
        AdminCsrfToken::get();

        $this->assertFalse($this->request->checkSession());
        $this->assertSame([], $_POST);
    }
}
