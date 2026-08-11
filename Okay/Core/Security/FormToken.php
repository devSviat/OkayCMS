<?php

namespace Okay\Core\Security;

/**
 * Одноразовий токен форми вітрини.
 *
 * Захищає не від підробки запиту - це робить CustomerCsrfToken - а від
 * повтору того самого: подвійний клік, F5, повтор після таймауту.
 *
 * Кожен рендер форми отримує власний токен, а сесія тримає перелік уже
 * використаних. Зворотний порядок - видати один токен і чекати саме на нього -
 * не працює: форма зворотного дзвінка стоїть на кожній сторінці, тож після
 * першої заявки кожна раніше відкрита вкладка тримала б мертвий токен, і її
 * заявка зникла б, хоч дані інші.
 *
 * Токен не підтверджує походження запиту, тому вигаданий зловмисником
 * пройде - і це нормально: справжність перевіряє CustomerCsrfToken.
 */
class FormToken
{
    /** Використані токени кожної форми: токен => результат тієї відправки. */
    const SESSION_KEY = 'form_tokens_used';

    /** Останній прийнятий відбиток кожної форми - для тем без токена. */
    const FINGERPRINT_KEY = 'form_fingerprints';

    /**
     * Скільки живе відбиток там, де ціна дубля висока (замовлення).
     * Довге вікно тут виправдане: друге замовлення коштує грошей.
     */
    const FINGERPRINT_TTL = 600;

    /**
     * Скільки живе відбиток там, де ціна дубля низька (заявка, відгук).
     * Випадковий повтор стається за секунди, а не за десять хвилин, тож
     * довге вікно тут лише ковтало б навмисні повторні звернення.
     */
    const ACCIDENT_TTL = 60;

    /**
     * Скільки використаних токенів пам'ятати на форму. Переповнення означає,
     * що дуже давній повтор проскочить - помилка куди дешевша за втрату
     * даних, яку дав би обмежений перелік виданих токенів.
     */
    const MAX_USED = 20;

    const TOKEN_PATTERN = '/^[0-9a-f]{64}$/';

    /**
     * Ім'я форми тут не потрібне - токен просто випадковий. Розділення за
     * формами живе в consume(): перелік використаних свій у кожної.
     */
    public static function get($form)
    {
        return bin2hex(random_bytes(32));
    }

    public static function consume($form, $token)
    {
        if (!self::isWellFormed($token)) {
            return false;
        }

        $used = self::used($form);

        if (array_key_exists((string)$token, $used)) {
            return false;
        }

        $used[(string)$token] = null;
        $_SESSION[self::SESSION_KEY][$form] = array_slice($used, -self::MAX_USED, null, true);

        return true;
    }

    public static function isWellFormed($token)
    {
        return is_string($token) && (bool)preg_match(self::TOKEN_PATTERN, $token);
    }

    /**
     * Записує, чим завершилась відправка з цим токеном - наприклад, адресу
     * створеного замовлення. Повтор із тим самим токеном отримає рівно її, а
     * не «останнє замовлення сесії», яким могло бути й чуже.
     */
    public static function remember($form, $token, $result)
    {
        if (!self::isWellFormed($token)) {
            return;
        }

        $used = self::used($form);
        $used[(string)$token] = $result;

        $_SESSION[self::SESSION_KEY][$form] = array_slice($used, -self::MAX_USED, null, true);
    }

    /**
     * @return mixed null, якщо токен невідомий або та відправка нічим не
     *               завершилась - тобто попередня спроба обірвалась
     */
    public static function recall($form, $token)
    {
        if (!self::isWellFormed($token)) {
            return null;
        }

        return self::used($form)[(string)$token] ?? null;
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
     * @param int    $ttl     скільки секунд відбиток вважається повтором
     */
    public static function accept($form, $token, $payload, $ttl = self::FINGERPRINT_TTL)
    {
        if (self::isWellFormed($token)) {
            return self::consume($form, $token);
        }

        return self::consumeFingerprint($form, self::fingerprintOf($payload), $ttl);
    }

    /**
     * Запасний шлях для тем без поля form_token: те саме тіло протягом TTL
     * вважається повтором. Точність нижча за токен.
     */
    public static function consumeFingerprint($form, $fingerprint, $ttl = self::FINGERPRINT_TTL)
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
            'expires_at' => $now + (int)$ttl,
        ];

        return true;
    }

    /**
     * @param mixed $payload дані форми: об'єкт, масив або будь-яка їх комбінація
     */
    public static function fingerprintOf($payload)
    {
        $encoded = json_encode(self::normalize($payload));

        return is_string($encoded) ? hash('sha256', $encoded) : '';
    }

    /**
     * @return array<string, mixed>
     */
    private static function used($form)
    {
        $used = $_SESSION[self::SESSION_KEY][$form] ?? [];

        return is_array($used) ? $used : [];
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
