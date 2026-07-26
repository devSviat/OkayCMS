<?php

namespace Security;

use Okay\Core\Security\AdminCsrfToken;
use PHPUnit\Framework\TestCase;

class AdminCsrfTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
    }

    public function testTokenIsNotTheSessionIdAndIsStable()
    {
        $token = AdminCsrfToken::get();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
        $this->assertNotSame(session_id(), $token);
        $this->assertSame($token, AdminCsrfToken::get());
    }

    public function testCheckFailsClosed()
    {
        AdminCsrfToken::get();

        $this->assertFalse(AdminCsrfToken::check(null));
        $this->assertFalse(AdminCsrfToken::check(''));
        $this->assertFalse(AdminCsrfToken::check('wrong'));
        $this->assertFalse(AdminCsrfToken::check(str_repeat('a', 64)));
    }

    public function testCheckFailsWhenNoTokenWasIssued()
    {
        $this->assertFalse(AdminCsrfToken::check(str_repeat('a', 64)));
    }

    public function testRotateInvalidatesThePreviousToken()
    {
        $old = AdminCsrfToken::get();
        $new = AdminCsrfToken::rotate();

        $this->assertNotSame($old, $new);
        $this->assertFalse(AdminCsrfToken::check($old));
        $this->assertTrue(AdminCsrfToken::check($new));
    }

    public function testMalformedStoredTokenIsReplaced()
    {
        $_SESSION[AdminCsrfToken::SESSION_KEY] = 'legacy-session-id-value';

        $token = AdminCsrfToken::get();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
        $this->assertSame($token, $_SESSION[AdminCsrfToken::SESSION_KEY]);
    }

    public function testGuardRunsBeforeControllerDispatch()
    {
        $source = $this->backendIndex();

        $guard = strpos($source, '$request->checkSession()');
        $dispatch = strpos($source, "call_user_func_array([\$backend, \$methodName]");

        $this->assertIsInt($guard);
        $this->assertIsInt($dispatch);
        $this->assertLessThan($dispatch, $guard);
    }

    public function testSessionIdIsNoLongerPublishedAsTheToken()
    {
        $source = $this->backendIndex();

        $this->assertStringNotContainsString("\$_SESSION['id'] = session_id();", $source);
        $this->assertStringContainsString('AdminCsrfToken::get()', $source);
    }

    public function testRequestUsesConstantTimeComparison()
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/Okay/Core/Request.php');
        $this->assertIsString($source);

        $this->assertStringContainsString('AdminCsrfToken::check(', $source);
        $this->assertStringNotContainsString("\$_POST['session_id'] != session_id()", $source);
    }

    private function backendIndex()
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/backend/index.php');
        $this->assertIsString($source);

        return $source;
    }
}
