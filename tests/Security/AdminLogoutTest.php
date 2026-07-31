<?php

namespace Security;

use Okay\Core\Security\SessionNames;
use PHPUnit\Framework\TestCase;

class AdminLogoutTest extends TestCase
{
    /**
     * cookieParams() описує сесійну куку для session_set_cookie_params(), і її
     * 'lifetime' валить setcookie() з ValueError. Помилка не видно з коду -
     * лише фатал у відповіді.
     */
    public function testDeleteCookieUsesValidSetcookieOptions()
    {
        $this->assertArrayHasKey('lifetime', SessionNames::cookieParams());

        $params = SessionNames::expiredCookieParams();

        $this->assertArrayNotHasKey('lifetime', $params);
        $this->assertArrayHasKey('expires', $params);
        $this->assertLessThan(time(), $params['expires']);
    }

    public function testBackendEntryPointHandlesLogoutItself()
    {
        $source = $this->read('backend/index.php');

        $this->assertStringContainsString("post('logout')", $source);
        $this->assertStringContainsString('isPost()', $source);
        $this->assertStringContainsString('checkSession()', $source);
        $this->assertStringContainsString('destroyBackend()', $source);
    }

    /**
     * Вихід має бути постом із токеном: посиланням його викликав би будь-який
     * сторонній сайт, а вітринний ?logout бекендової сесії взагалі не бачить.
     */
    public function testAdminTemplatePostsLogoutWithAToken()
    {
        $source = $this->read('backend/design/html/index.tpl');

        $this->assertSame(3, substr_count($source, 'name="logout"'));
        $this->assertSame(0, substr_count($source, '?logout'));
        $this->assertSame(3, substr_count($source, 'name="session_id" value="{$smarty.session.id|escape}"'));
    }

    private function read($file)
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $file);
        $this->assertIsString($source, $file);

        return $source;
    }
}
