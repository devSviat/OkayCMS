<?php

namespace Okay\Core\Security;

/**
 * Розділяє простори сесій вітрини та адмін-панелі.
 *
 * Раніше ім'я сесії обчислювалось як md5(User-Agent): воно було спільним
 * для фронту й бекенду, тож сесія покупця і сесія менеджера жили в одній
 * куці, і залежало від заголовка запиту.
 */
class SessionNames
{
    const FRONTEND = 'okay_sid';
    const BACKEND  = 'okay_admin_sid';

    /**
     * Кешується на весь запит: обчислюється один раз і ніколи не пишеться
     * в сесію вітрини, інакше вихід менеджера з бекенду не забирав би
     * привілеї на вітрині одразу ж, на наступному запиті.
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
     * Чи залогінений менеджер у бекенді — визначається за окремою,
     * бекендовою сесією (окей_admin_sid), а не за $_SESSION['admin']
     * вітрини: після розділення просторів сесій (982c20b) вітрина
     * ніколи не бачить бекендового $_SESSION.
     *
     * Викликати ДО SessionNames::startFrontend() — одночасно активною
     * може бути лише одна сесія.
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

        // Кука вітрини — щоб після читання бекендової сесії відновити саме
        // її ідентифікатор: PHP запам'ятовує, що session_id() уже викликали
        // явно в цьому запиті, і перестає сам читати ідентифікатор з куки
        // при наступному session_start(). Без цього SessionNames::startFrontend()
        // отримав би або чужий (бекендовий) ідентифікатор, або новий випадковий —
        // і "загубив" би сесію відвідувача вітрини.
        $frontendCookieSessionId = $_COOKIE[self::FRONTEND] ?? null;
        $previousName            = session_name();
        // 'use_cookies' і 'use_strict_mode' в опціях session_start() — це те
        // саме, що ini_set(): значення лишається чинним і після завершення
        // читання. Якщо не повернути їх назад, SessionNames::startFrontend(),
        // що йде одразу після, впаде на session_set_cookie_params() (use_cookies
        // вимкнено) або матиме інший режим строгості, ніж було до нашого виклику.
        $previousUseCookies  = ini_get('session.use_cookies');
        $previousStrictMode  = ini_get('session.use_strict_mode');

        $login = null;
        try {
            session_name(self::BACKEND);
            session_id($cookieSessionId);

            // read_and_close: не тримаємо лок сесії. use_cookies => false:
            // жодного Set-Cookie для бекендової куки в межах запиту вітрини.
            // use_strict_mode: підроблений/неіснуючий ідентифікатор не буде
            // "прийнятий" — PHP згенерує для нього власний, а дані лишаться
            // порожніми.
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
