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

    public static function startFrontend()
    {
        self::start(self::FRONTEND);
    }

    public static function startBackend()
    {
        self::start(self::BACKEND);
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
