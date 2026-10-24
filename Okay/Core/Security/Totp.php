<?php

namespace Okay\Core\Security;

/**
 * TOTP за RFC 6238: HMAC-SHA1, крок 30 с, 6 цифр — те, що читають Google,
 * Microsoft і Proton Authenticator. Звірено з тест-векторами RFC 6238
 * (додаток B) у TotpTest.
 */
final class Totp
{
    public const int PERIOD = 30;
    public const int DIGITS = 6;
    /** Допуск на розбіжність годинників: ±1 крок. */
    public const int WINDOW = 1;
    public const int SECRET_BYTES = 20;

    private const string BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function newSecret(): string
    {
        return random_bytes(self::SECRET_BYTES);
    }

    public static function step(int $timestamp): int
    {
        return intdiv($timestamp, self::PERIOD);
    }

    /** Код для кроку; $digits більше за 6 — лише для звірки з векторами RFC. */
    public static function code(string $secret, int $step, int $digits = self::DIGITS): string
    {
        $hmac = hash_hmac('sha1', pack('J', $step), $secret, true);
        $offset = ord($hmac[19]) & 0x0f;
        $binary = ((ord($hmac[$offset]) & 0x7f) << 24)
            | (ord($hmac[$offset + 1]) << 16)
            | (ord($hmac[$offset + 2]) << 8)
            | ord($hmac[$offset + 3]);

        return str_pad((string)($binary % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
    }

    /**
     * Крок, для якого код чинний у вікні ±WINDOW навколо $timestamp, або null.
     * Перебираються всі кроки вікна без раннього виходу й порівнюються через
     * hash_equals: час відповіді не підказує, котрий крок збігся.
     */
    public static function matchingStep(string $secret, string $code, int $timestamp): ?int
    {
        if (!preg_match('/^\d{' . self::DIGITS . '}$/', $code)) {
            return null;
        }

        $current = self::step($timestamp);
        $matched = null;
        for ($offset = -self::WINDOW; $offset <= self::WINDOW; $offset++) {
            if (hash_equals(self::code($secret, $current + $offset), $code) && $matched === null) {
                $matched = $current + $offset;
            }
        }

        return $matched;
    }

    public static function base32Encode(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) {
            $encoded .= self::BASE32[bindec(str_pad($chunk, 5, '0'))];
        }

        return $encoded;
    }

    /** @return string|null null — не Base32 */
    public static function base32Decode(string $encoded): ?string
    {
        $encoded = strtoupper(str_replace([' ', '='], '', $encoded));
        if ($encoded === '' || strspn($encoded, self::BASE32) !== strlen($encoded)) {
            return null;
        }

        $bits = '';
        foreach (str_split($encoded) as $char) {
            $bits .= str_pad(decbin(strpos(self::BASE32, $char)), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $bytes .= chr(bindec($chunk));
            }
        }

        return $bytes;
    }

    /** otpauth://-адреса для QR: застосунок бачить магазин і логін. */
    public static function provisioningUri(string $secret, string $issuer, string $account): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode($account)
            . '?secret=' . self::base32Encode($secret)
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=' . self::DIGITS . '&period=' . self::PERIOD;
    }
}
