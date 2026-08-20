<?php

namespace Okay\Core\Security;

/**
 * Розділяє простори сесій вітрини та адмін-панелі: без цього сесія покупця
 * і сесія менеджера жили б в одній куці.
 */
class SessionNames
{
    const FRONTEND = 'okay_sid';
    const BACKEND  = 'okay_admin_sid';

    /**
     * Мова панелі для сторінки входу. Переживає вихід навмисно: після виходу
     * менеджер бачить форму входу і має бачити її своєю мовою.
     */
    const ADMIN_LANG_COOKIE = 'admin_lang';

    /**
     * Ніколи не пишеться в сесію вітрини, інакше вихід менеджера з бекенду не
     * забирав би привілеї на вітрині одразу ж.
     */
    private static bool $adminChecked = false;
    private static ?string $adminLogin = null;

    public static function startFrontend()
    {
        self::start(self::FRONTEND);
    }

    public static function startBackend()
    {
        self::start(self::BACKEND);
    }

    /**
     * Визначається за окремою бекендовою сесією, а не за $_SESSION['admin']
     * вітрини: вітрина бекендового $_SESSION не бачить ніколи.
     *
     * Викликати ДО startFrontend() — активною може бути лише одна сесія.
     */
    public static function isAdmin(): bool
    {
        return self::adminLogin() !== null;
    }

    /**
     * @return string|null логін менеджера з бекендової сесії, або null,
     *                      якщо валідної бекендової сесії немає.
     */
    public static function adminLogin(): ?string
    {
        if (!self::$adminChecked) {
            self::$adminChecked = true;
            self::$adminLogin   = self::readBackendAdminLogin();
        }

        return self::$adminLogin;
    }

    /**
     * Читає $_SESSION['admin'] з бекендової сесії, не активуючи і не
     * підмінюючи сесію поточного (вітринного) запиту.
     */
    private static function readBackendAdminLogin(): ?string
    {
        // Якщо якась сесія вже активна — ми викликані не там, де очікувалось
        // (до запуску сесії вітрини), і чіпати чужу активну сесію небезпечно.
        if (session_status() === PHP_SESSION_ACTIVE) {
            return null;
        }

        $cookieSessionId = $_COOKIE[self::BACKEND] ?? null;
        if (!is_string($cookieSessionId) || $cookieSessionId === '' || !self::isValidSessionId($cookieSessionId)) {
            return null;
        }

        // Запам'ятовуємо куку вітрини: після явного session_id() PHP перестає
        // сам читати ідентифікатор з куки, і startFrontend() отримав би чужий
        // або новий випадковий — тобто загубив би сесію відвідувача.
        $frontendCookieSessionId = $_COOKIE[self::FRONTEND] ?? null;
        $previousName            = session_name();
        // Опції session_start() діють як ini_set() і лишаються чинними після
        // читання. Не повернувши їх, startFrontend() упаде на
        // session_set_cookie_params() або отримає інший режим строгості.
        $previousUseCookies  = ini_get('session.use_cookies');
        $previousStrictMode  = ini_get('session.use_strict_mode');

        $login = null;
        try {
            session_name(self::BACKEND);
            session_id($cookieSessionId);

            // read_and_close — не тримаємо лок. use_cookies => false — жодного
            // Set-Cookie бекендової куки в запиті вітрини. use_strict_mode —
            // підроблений ідентифікатор не буде прийнятий.
            $started = @session_start([
                'read_and_close'  => true,
                'use_cookies'     => false,
                'use_strict_mode' => true,
            ]);

            if ($started && !empty($_SESSION['admin']) && is_string($_SESSION['admin'])) {
                $login = $_SESSION['admin'];
            }
        } finally {
            session_name($previousName);
            session_id($frontendCookieSessionId !== null && $frontendCookieSessionId !== '' ? $frontendCookieSessionId : '');

            if ($previousUseCookies !== false) {
                ini_set('session.use_cookies', $previousUseCookies);
            }
            if ($previousStrictMode !== false) {
                ini_set('session.use_strict_mode', $previousStrictMode);
            }
        }

        return $login;
    }

    private static function isValidSessionId(string $id): bool
    {
        $length = (int)ini_get('session.sid_length');
        if ($length <= 0) {
            $length = 32;
        }

        return (bool)preg_match('/^[a-zA-Z0-9,\-]{' . $length . '}$/', $id);
    }

    /**
     * Мемоїзація тримається рівно один запит. Якщо процес переживає запит,
     * логін першого менеджера робив би isAdmin() істинним для всіх наступних
     * анонімних відвідувачів - тобто був би обходом автентифікації.
     */
    public static function resetRequestState(): void
    {
        self::$adminChecked = false;
        self::$adminLogin   = null;
    }

    /**
     * Викликається при зміні рівня привілеїв: вхід, вихід, скидання пароля.
     */
    public static function regenerate()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /**
     * Повний вихід менеджера: дані, ідентифікатор і кука бекендової сесії.
     * Викликати при активній бекендовій сесії.
     */
    public static function destroyBackend()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];
        session_regenerate_id(true);
        session_destroy();

        self::deleteCookie(self::BACKEND);
    }

    /**
     * cookieParams() описує сесійну куку для session_set_cookie_params(), і
     * її 'lifetime' для setcookie() - невалідна опція (ValueError).
     */
    public static function deleteCookie($name)
    {
        setcookie($name, '', self::expiredCookieParams());
    }

    public static function expiredCookieParams()
    {
        $params = self::cookieParams();
        unset($params['lifetime']);
        $params['expires'] = time() - 3600;

        return $params;
    }

    public static function cookieParams()
    {
        return [
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }

    public static function isHttps()
    {
        if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
            return true;
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])
            && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https'
        ) {
            return true;
        }

        return false;
    }

    private static function start($name)
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name($name);
        session_set_cookie_params(self::cookieParams());
        session_start();
    }
}
