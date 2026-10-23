<?php

namespace Security;

use Okay\Core\Security\CustomerCsrfToken;
use PHPUnit\Framework\TestCase;

class CustomerCsrfTokenTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
        $_COOKIE = [];
    }

    public function testTokenIsOpaqueAndStable()
    {
        $token = CustomerCsrfToken::get();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
        $this->assertNotSame(session_id(), $token);
        $this->assertSame($token, CustomerCsrfToken::get());
        $this->assertTrue(CustomerCsrfToken::check($token));
    }

    public function testCheckFailsClosed()
    {
        CustomerCsrfToken::get();

        $this->assertFalse(CustomerCsrfToken::check(null));
        $this->assertFalse(CustomerCsrfToken::check(''));
        $this->assertFalse(CustomerCsrfToken::check('wrong'));
        $this->assertFalse(CustomerCsrfToken::check(str_repeat('a', 64)));
    }

    public function testTokenSurvivesSessionResetViaCookie()
    {
        $token = CustomerCsrfToken::get();

        // Зміна імені сесії на деплої обнуляє серверний стан,
        // але вже відрендерена форма має лишитись робочою.
        $_SESSION = [];

        $this->assertTrue(CustomerCsrfToken::check($token));
        $this->assertSame($token, CustomerCsrfToken::get());
    }

    public function testRotateInvalidatesThePreviousToken()
    {
        $old = CustomerCsrfToken::get();
        $new = CustomerCsrfToken::rotate();

        $this->assertNotSame($old, $new);
        $this->assertTrue(CustomerCsrfToken::check($new));
        $this->assertFalse(CustomerCsrfToken::check($old));
    }

    public function testMalformedCookieIsIgnored()
    {
        $_COOKIE[CustomerCsrfToken::COOKIE_NAME] = 'not-a-token';

        $token = CustomerCsrfToken::get();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
        $this->assertFalse(CustomerCsrfToken::check('not-a-token'));
    }

    public function testCheckIsFalseWhenNothingWasIssued()
    {
        $this->assertFalse(CustomerCsrfToken::check(str_repeat('b', 64)));
    }

    /**
     * Підкинута кука (піддомен, http без HSTS) не має перемагати токен сесії:
     * інакше нападник обирав би токен сам.
     */
    public function testCookieDoesNotOverrideTheSessionToken()
    {
        $own = CustomerCsrfToken::get();
        $planted = str_repeat('c', 64);
        $_COOKIE[CustomerCsrfToken::COOKIE_NAME] = $planted;

        $this->assertFalse(CustomerCsrfToken::check($planted));
        $this->assertTrue(CustomerCsrfToken::check($own));
    }

    /** Кука, що розійшлась із сесією, вирівнюється: AJAX тем бере токен саме з неї. */
    public function testDivergedCookieIsResetToTheSessionToken()
    {
        $own = CustomerCsrfToken::get();
        $_COOKIE[CustomerCsrfToken::COOKIE_NAME] = str_repeat('d', 64);

        $this->assertSame($own, CustomerCsrfToken::get());
        $this->assertSame($own, $_COOKIE[CustomerCsrfToken::COOKIE_NAME]);
    }
}
