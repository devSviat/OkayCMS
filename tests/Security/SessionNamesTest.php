<?php

namespace Security;

use Okay\Core\Security\SessionNames;
use PHPUnit\Framework\TestCase;

class SessionNamesTest extends TestCase
{
    public function testFrontendAndBackendNamespacesDiffer()
    {
        $this->assertSame('okay_sid', SessionNames::FRONTEND);
        $this->assertSame('okay_admin_sid', SessionNames::BACKEND);
        $this->assertNotSame(SessionNames::FRONTEND, SessionNames::BACKEND);
    }

    public function testCookieParamsAreHardened()
    {
        $params = SessionNames::cookieParams();

        $this->assertTrue($params['httponly']);
        $this->assertSame('Lax', $params['samesite']);
        $this->assertSame('/', $params['path']);
        $this->assertSame(0, $params['lifetime']);
        $this->assertArrayHasKey('secure', $params);
    }

    public function testHttpsDetection()
    {
        $server = $_SERVER;

        unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO']);
        $this->assertFalse(SessionNames::isHttps());

        $_SERVER['HTTPS'] = 'off';
        $this->assertFalse(SessionNames::isHttps());

        $_SERVER['HTTPS'] = 'on';
        $this->assertTrue(SessionNames::isHttps());

        unset($_SERVER['HTTPS']);
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        $this->assertTrue(SessionNames::isHttps());

        $_SERVER = $server;
    }

    /**
     * @dataProvider entrypointProvider
     */
    public function testEntrypointsNoLongerDeriveSessionNameFromUserAgent($file)
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $file);

        $this->assertIsString($source, $file);
        $this->assertStringNotContainsString("session_name(md5(\$_SERVER['HTTP_USER_AGENT']))", $source, $file);

        // Точка входа либо стартует сессию сама, либо делегирует это
        // okay_access.php, который вызывает SessionNames::startBackend().
        $this->assertTrue(
            strpos($source, 'SessionNames::') !== false || strpos($source, 'okay_access.php') !== false,
            $file . ': neither starts the session nor delegates to okay_access.php'
        );
    }

    public function entrypointProvider()
    {
        return [
            'storefront'   => ['index.php'],
            'backend'      => ['backend/index.php'],
            'ajax config'  => ['backend/ajax/configure.php'],
            'admintooltip' => ['backend/design/js/admintooltip/admintooltip.php'],
            'upload'       => ['backend/design/js/filemanager/UploadHandler.php'],
            'dialog'       => ['backend/design/js/filemanager/dialog.php'],
            'fm config'    => ['backend/design/js/filemanager/config/config.php'],
            'backend files'=> ['backend/files/index.php'],
        ];
    }

    /**
     * @dataProvider privilegeTransitionProvider
     */
    public function testPrivilegeTransitionsRegenerateTheSessionId($file, $assignment)
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $file);
        $this->assertIsString($source, $file);

        $regenerate = strpos($source, 'SessionNames::regenerate();');
        $login = strpos($source, $assignment);

        $this->assertIsInt($regenerate, $file . ': no regenerate call');
        $this->assertIsInt($login, $file . ': no session assignment');
        $this->assertLessThan($login, $regenerate, $file);
    }

    public function privilegeTransitionProvider()
    {
        return [
            'admin login'    => ['backend/Controllers/AuthAdmin.php', "\$_SESSION['admin'] = \$manager->login;"],
            'customer reset' => ['Okay/Controllers/UserController.php', "\$_SESSION['user_id'] = "],
        ];
    }
}
