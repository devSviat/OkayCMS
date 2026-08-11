<?php

namespace Okay\Core\Security;

/**
 * Одноразовий токен форми вітрини.
 *
 * Захищає не від підробки запиту - це робить CustomerCsrfToken - а від
 * повтору того самого: подвійний клік, F5, повтор після таймауту.
 *
 * Токени різних форм незалежні: заявка на дзвінок не гасить коментар,
 * відправлений із тієї ж сторінки.
 */
class FormToken
{
    const SESSION_KEY = 'form_tokens';

    /** Останній прийнятий відбиток кожної форми - для тем без токена. */
    const FINGERPRINT_KEY = 'form_fingerprints';

    const FINGERPRINT_TTL = 600;

    const TOKEN_PATTERN = '/^[0-9a-f]{64}$/';

    public static function get($form)
    {
        $stored = $_SESSION[self::SESSION_KEY][$form] ?? null;

        if (is_string($stored) && self::isWellFormed($stored)) {
            return $stored;
        }

        return self::rotate($form);
    }

    public static function rotate($form)
    {
        $_SESSION[self::SESSION_KEY][$form] = bin2hex(random_bytes(32));

        return $_SESSION[self::SESSION_KEY][$form];
    }

    public static function consume($form, $token)
    {
        if (!self::isWellFormed($token)) {
            return false;
        }

        $stored = $_SESSION[self::SESSION_KEY][$form] ?? null;

        if (!is_string($stored) || !hash_equals($stored, (string)$token)) {
            return false;
        }

        unset($_SESSION[self::SESSION_KEY][$form]);

        return true;
    }

    public static function isWellFormed($token)
    {
        return is_string($token) && (bool)preg_match(self::TOKEN_PATTERN, $token);
    }

    /**
     * Єдина точка рішення «це нова відправка чи повтор».
     *
     * Токен - основний шлях, відбиток - запасний, і лише за відсутності
     * токена. Змішувати їх не можна: відбиток рахується з даних, які сама
     * дія може змінити (оформлення очищає кошик), тож після успішного
     * запису той самий повтор дав би вже інший відбиток і проскочив.
     *
     * @param string $form    ім'я форми
     * @param mixed  $token   значення поля form_token, якщо тема його шле
     * @param mixed  $payload дані форми для запасного відбитка
     */
    public static function accept($form, $token, $payload)
    {
        if (self::isWellFormed($token)) {
            return self::consume($form, $token);
        }

        return self::consumeFingerprint($form, self::fingerprintOf($payload));
    }

    /**
     * Запасний шлях для тем без поля form_token: те саме тіло протягом TTL
     * вважається повтором. Точність нижча за токен.
     */
    public static function consumeFingerprint($form, $fingerprint)
    {
        if (!is_string($fingerprint) || $fingerprint === '') {
            return true;
        }

        $stored = $_SESSION[self::FINGERPRINT_KEY][$form] ?? null;
        $now = time();

        if (is_array($stored)
            && isset($stored['value'], $stored['expires_at'])
            && is_string($stored['value'])
            && (int)$stored['expires_at'] >= $now
            && hash_equals($stored['value'], $fingerprint)
        ) {
            return false;
        }

        $_SESSION[self::FINGERPRINT_KEY][$form] = [
            'value' => $fingerprint,
            'expires_at' => $now + self::FINGERPRINT_TTL,
        ];

        return true;
    }

    /**
     * @param mixed $payload дані форми: об'єкт, масив або будь-яка їх комбінація
     */
    public static function fingerprintOf($payload)
    {
        $normalized = self::normalize($payload);
        $encoded = json_encode($normalized);

        return is_string($encoded) ? hash('sha256', $encoded) : '';
    }

    /**
     * Порядок ключів не має впливати на відбиток: два однакові за складом
     * запити мають дати той самий хеш.
     */
    private static function normalize($value)
    {
        if (is_object($value)) {
            $value = (array)$value;
        }

        if (!is_array($value)) {
            return $value;
        }

        ksort($value);

        return array_map([self::class, 'normalize'], $value);
    }
}
