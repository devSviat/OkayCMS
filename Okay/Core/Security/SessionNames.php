<?php

namespace Okay\Core\Security;

/**
 * Разделяет пространства сессий витрины и админ-панели.
 *
 * Раньше имя сессии вычислялось как md5(User-Agent): оно было общим для
 * фронта и бэкенда, поэтому сессия покупателя и сессия менеджера жили в
 * одной куке, и зависело от заголовка запроса.
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
     * Вызывается при смене уровня привилегий: вход, выход, сброс пароля.
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
