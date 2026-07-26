<?php

namespace Okay\Core\Security;

/**
 * CSRF-токен вітрини.
 *
 * Значення дублюється в SameSite=Lax куку: при зміні імені сесії
 * (див. SessionNames) серверний стан губиться, а форма, відрендерена
 * до оновлення, має лишитись робочою.
 */
class CustomerCsrfToken
{
    const SESSION_KEY = 'customer_csrf_token';
    const COOKIE_NAME = 'okay_csrf';

    const TOKEN_PATTERN = '/^[0-9a-f]{64}$/';

    public static function get()
    {
        $fromSession = self::sessionToken();
        if ($fromSession !== null) {
            return $fromSession;
        }

        $fromCookie = self::cookieToken();
        if ($fromCookie !== null) {
            $_SESSION[self::SESSION_KEY] = $fromCookie;

            return $fromCookie;
        }

        return self::rotate();
    }

    public static function rotate()
    {
        $token = bin2hex(random_bytes(32));

        $_SESSION[self::SESSION_KEY] = $token;
        $_COOKIE[self::COOKIE_NAME] = $token;

        if (!headers_sent()) {
            setcookie(self::COOKIE_NAME, $token, [
                'expires'  => 0,
                'path'     => '/',
                'secure'   => SessionNames::isHttps(),
                // Куку читають storefront-скрипти, щоб підставити токен
                // в AJAX-мутації. Це не облікові дані: вона лише має
                // збігтися з тим, що сервер уже знає.
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
        }

        return $token;
    }

    public static function check($token)
    {
        if (!self::isWellFormed($token)) {
            return false;
        }

        foreach ([self::sessionToken(), self::cookieToken()] as $known) {
            if ($known !== null && hash_equals($known, (string)$token)) {
                return true;
            }
        }

        return false;
    }

    private static function sessionToken()
    {
        if (empty($_SESSION[self::SESSION_KEY]) || !self::isWellFormed($_SESSION[self::SESSION_KEY])) {
            return null;
        }

        return (string)$_SESSION[self::SESSION_KEY];
    }

    private static function cookieToken()
    {
        if (empty($_COOKIE[self::COOKIE_NAME]) || !self::isWellFormed($_COOKIE[self::COOKIE_NAME])) {
            return null;
        }

        return (string)$_COOKIE[self::COOKIE_NAME];
    }

    private static function isWellFormed($token)
    {
        return is_string($token) && (bool)preg_match(self::TOKEN_PATTERN, $token);
    }
}
