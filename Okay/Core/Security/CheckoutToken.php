<?php

namespace Okay\Core\Security;

/**
 * Одноразовий токен оформлення замовлення.
 *
 * Захищає не від підробки запиту - це робить CustomerCsrfToken - а від
 * повтору того самого: подвійний клік, F5, повтор після таймауту.
 */
class CheckoutToken
{
    const SESSION_KEY = 'checkout_token';

    /** Останній прийнятий відбиток замовлення - для тем без токена. */
    const FINGERPRINT_KEY = 'checkout_fingerprint';

    const FINGERPRINT_TTL = 600;

    const TOKEN_PATTERN = '/^[0-9a-f]{64}$/';

    public static function get()
    {
        if (!empty($_SESSION[self::SESSION_KEY]) && self::isWellFormed($_SESSION[self::SESSION_KEY])) {
            return $_SESSION[self::SESSION_KEY];
        }

        return self::rotate();
    }

    public static function rotate()
    {
        $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));

        return $_SESSION[self::SESSION_KEY];
    }

    public static function consume($token)
    {
        if (!self::isWellFormed($token)) {
            return false;
        }

        if (empty($_SESSION[self::SESSION_KEY]) || !is_string($_SESSION[self::SESSION_KEY])) {
            return false;
        }

        if (!hash_equals($_SESSION[self::SESSION_KEY], (string)$token)) {
            return false;
        }

        unset($_SESSION[self::SESSION_KEY]);

        return true;
    }

    public static function isWellFormed($token)
    {
        return is_string($token) && (bool)preg_match(self::TOKEN_PATTERN, $token);
    }

    /**
     * Запасний шлях для тем без checkout_token: той самий склад замовлення
     * протягом TTL вважається повтором. Точність нижча за токен.
     */
    public static function consumeFingerprint($fingerprint)
    {
        if (!is_string($fingerprint) || $fingerprint === '') {
            return true;
        }

        $stored = $_SESSION[self::FINGERPRINT_KEY] ?? null;
        $now = time();

        if (is_array($stored)
            && isset($stored['value'], $stored['expires_at'])
            && is_string($stored['value'])
            && (int)$stored['expires_at'] >= $now
            && hash_equals($stored['value'], $fingerprint)
        ) {
            return false;
        }

        $_SESSION[self::FINGERPRINT_KEY] = [
            'value' => $fingerprint,
            'expires_at' => $now + self::FINGERPRINT_TTL,
        ];

        return true;
    }

    /**
     * @param object $order дані замовлення з форми
     * @param array<int|string, mixed> $cartItems вміст кошика: варіант => кількість
     */
    public static function fingerprintOf($order, array $cartItems)
    {
        ksort($cartItems);

        $payload = json_encode([(array)$order, $cartItems]);

        return is_string($payload) ? hash('sha256', $payload) : '';
    }
}
