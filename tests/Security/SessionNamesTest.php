<?php

namespace Security;

use Okay\Core\Security\SessionNames;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

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

    #[DataProvider('entrypointProvider')]
    public function testEntrypointsNoLongerDeriveSessionNameFromUserAgent($file)
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $file);

        $this->assertIsString($source, $file);
        $this->assertStringNotContainsString("session_name(md5(\$_SERVER['HTTP_USER_AGENT']))", $source, $file);

        // Точка входу або стартує сесію сама, або делегує це
        // okay_access.php, який викликає SessionNames::startBackend().
        $this->assertTrue(
            strpos($source, 'SessionNames::') !== false || strpos($source, 'okay_access.php') !== false,
            $file . ': neither starts the session nor delegates to okay_access.php'
        );
    }

    public static function entrypointProvider()
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

    #[DataProvider('privilegeTransitionProvider')]
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

    public static function privilegeTransitionProvider()
    {
        return [
            'admin login'    => ['backend/Controllers/AuthAdmin.php', "\$_SESSION['admin'] = \$manager->login;"],
            'customer reset' => ['Okay/Controllers/UserController.php', "\$_SESSION['user_id'] = "],
        ];
    }

    #[RunInSeparateProcess]
    public function testNoBackendCookieMeansNoAdmin()
    {
        unset($_COOKIE[SessionNames::BACKEND]);

        $this->assertFalse(SessionNames::isAdmin());
        $this->assertNull(SessionNames::adminLogin());
        $this->assertSame(
            PHP_SESSION_NONE,
            session_status(),
            'no session should be started at all when the backend cookie is absent'
        );
    }

    #[RunInSeparateProcess]
    public function testMalformedSessionIdGrantsNothing()
    {
        // Явно неправильна форма: не той алфавіт/довжина, спроба ін'єкції тощо.
        $_COOKIE[SessionNames::BACKEND] = "'; DROP TABLE managers; --";

        $this->assertFalse(SessionNames::isAdmin());
        $this->assertNull(SessionNames::adminLogin());
    }

    #[RunInSeparateProcess]
    public function testForgedButWellFormedSessionIdGrantsNothing()
    {
        // Синтаксично коректний ідентифікатор (правильний алфавіт і довжина),
        // якому просто не відповідає жодна реальна сесія на диску.
        $length = (int)ini_get('session.sid_length') ?: 32;
        $_COOKIE[SessionNames::BACKEND] = str_repeat('a', $length);

        $this->assertFalse(SessionNames::isAdmin());
        $this->assertNull(SessionNames::adminLogin());
    }

    #[RunInSeparateProcess]
    public function testGenuineBackendSessionIsSeen()
    {
        $sessionId = $this->createBackendSession('some_manager_login');
        $_COOKIE[SessionNames::BACKEND] = $sessionId;

        try {
            $this->assertTrue(SessionNames::isAdmin());
            $this->assertSame('some_manager_login', SessionNames::adminLogin());
        } finally {
            $this->destroySessionFile($sessionId);
        }
    }

    #[RunInSeparateProcess]
    public function testAdminAwarenessIsNotPersistedIntoTheFrontendSession()
    {
        $sessionId = $this->createBackendSession('some_manager_login');
        $_COOKIE[SessionNames::BACKEND] = $sessionId;

        try {
            // Той самий порядок, що і в index.php: спочатку читаємо бекенд,
            // тоді стартуємо сесію вітрини.
            $this->assertTrue(SessionNames::isAdmin());

            SessionNames::startFrontend();

            $this->assertArrayNotHasKey(
                'admin',
                $_SESSION,
                "вихід менеджера з бекенду має одразу забирати привілеї на вітрині, " .
                "а не після того, як хтось випадково запише 'admin' в сесію вітрини"
            );

            $frontendSessionId = session_id();
            session_write_close();
            $this->destroySessionFile($frontendSessionId);
        } finally {
            $this->destroySessionFile($sessionId);
        }
    }

    private function createBackendSession(string $login): string
    {
        session_name(SessionNames::BACKEND);
        session_start();
        $_SESSION['admin'] = $login;
        $id = session_id();
        session_write_close();

        return $id;
    }

    private function destroySessionFile(?string $id): void
    {
        if (empty($id)) {
            return;
        }

        $path = session_save_path() ?: sys_get_temp_dir();
        @unlink(rtrim($path, '/') . '/sess_' . $id);
    }
}
