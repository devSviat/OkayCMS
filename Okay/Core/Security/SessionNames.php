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
    /** Відповідь уже визначено й запам'ятовано до кінця запиту. */
    private static bool $adminChecked = false;
    /** Звірений із реєстром логін або null. */
    private static ?string $adminLogin = null;

    private static bool $backendRead = false;
    /** @var array{0: string, 1: string}|null логін і ідентифікатор бекендової сесії, ще не звірені з реєстром */
    private static ?array $backendSession = null;
    /** @var (callable(string, string): bool)|null */
    private static $adminSessionValidator = null;

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
     * вітрини: вітрина бекендового $_SESSION не бачить ніколи. Адмін — лише
     * коли сесію підтвердив реєстр (setAdminSessionValidator).
     *
     * Бекендову сесію треба прочитати ДО startFrontend() (preloadBackendSession):
     * активною може бути лише одна сесія.
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
        if (self::$adminChecked) {
            return self::$adminLogin;
        }

        self::preloadBackendSession();
        if (self::$backendSession === null) {
            self::$adminChecked = true;

            return self::$adminLogin = null;
        }

        // Без валідатора відповідь «не адмін», і вона не запам'ятовується:
        // після реєстрації валідатора той самий запит перевірить сесію.
        if (self::$adminSessionValidator === null) {
            return null;
        }

        [$login, $sessionId] = self::$backendSession;
        self::$adminChecked = true;

        return self::$adminLogin = (self::$adminSessionValidator)($login, $sessionId) === true ? $login : null;
    }

    /**
     * Читає бекендову сесію, поки інша не активна. Звіряє з реєстром уже
     * adminLogin(), коли валідатор зареєстровано.
     *
     * Викликати ДО startFrontend() — активною може бути лише одна сесія.
     */
    public static function preloadBackendSession(): void
    {
        if (!self::$backendRead) {
            self::$backendRead = true;
            self::$backendSession = self::readBackendSession();
        }
    }

    /**
     * Перевірка пари (логін, ідентифікатор сесії) реєстром сесій
     * (ManagerSessions::isValid). Реєструє container.php.
     *
     * @param callable(string, string): bool $validator
     */
    public static function setAdminSessionValidator(callable $validator): void
    {
        self::$adminSessionValidator = $validator;
        self::$adminChecked = false;
        self::$adminLogin = null;
    }

    /**
     * Читає $_SESSION['admin'] з бекендової сесії, не активуючи і не
     * підмінюючи сесію поточного (вітринного) запиту.
     *
     * @return array{0: string, 1: string}|null
     */
    private static function readBackendSession(): ?array
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

        $session = null;
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
                $session = [$_SESSION['admin'], $cookieSessionId];
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

        return $session;
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

        // Нова кука у відповідь на міжсайтовий POST затерла б сесію відвідувача.
        // Сама сесія запиту потрібна - її читає перевірка токена; зайва лише кука.
        if (self::isCrossSitePostWithoutSession($name)) {
            @session_start(['use_cookies' => false, 'use_strict_mode' => true]);
            return;
        }

        // Строгий режим: ідентифікатор, якого сервер не видавав, замінюється
        // новим. Інакше підкинутий заздалегідь id (фіксація сесії) ставав би
        // сесією жертви. У коді, а не в php.ini, — щоб діяло на будь-якому хостингу.
        session_start(['use_strict_mode' => true]);
    }

    /**
     * Усі три умови разом. Власна форма постить із кукою й зі своїм Origin,
     * а серверні колбеки платіжних систем Origin не шлють узагалі.
     */
    private static function isCrossSitePostWithoutSession($name): bool
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return false;
        }

        if (!empty($_COOKIE[$name])) {
            return false;
        }

        $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
        if (empty($origin)) {
            return false;
        }

        return !RequestOrigin::matchesSite($origin, null, \Okay\Core\Request::getDomainWithProtocol());
    }
}
