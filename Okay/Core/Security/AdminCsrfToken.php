<?php

namespace Okay\Core\Security;

/**
 * CSRF-токен адмін-панелі.
 *
 * Зберігається в $_SESSION['id'], бо шаблони бекенду вже друкують це
 * значення як {$smarty.session.id} у прихованому полі session_id. Змінюється
 * лише саме значення: раніше там лежав ідентифікатор сесії, і він
 * потрапляв у HTML кожної сторінки адмінки.
 */
class AdminCsrfToken
{
    const SESSION_KEY = 'id';

    const TOKEN_PATTERN = '/^[0-9a-f]{64}$/';

    public static function get()
    {
        if (empty($_SESSION[self::SESSION_KEY]) || !self::isWellFormed($_SESSION[self::SESSION_KEY])) {
            return self::rotate();
        }

        return $_SESSION[self::SESSION_KEY];
    }

    public static function rotate()
    {
        $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));

        return $_SESSION[self::SESSION_KEY];
    }

    public static function check($token)
    {
        if (!self::isWellFormed($token)) {
            return false;
        }

        if (empty($_SESSION[self::SESSION_KEY]) || !self::isWellFormed($_SESSION[self::SESSION_KEY])) {
            return false;
        }

        return hash_equals((string)$_SESSION[self::SESSION_KEY], (string)$token);
    }

    private static function isWellFormed($token)
    {
        return is_string($token) && (bool)preg_match(self::TOKEN_PATTERN, $token);
    }
}
