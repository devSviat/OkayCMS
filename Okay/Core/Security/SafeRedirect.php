<?php

namespace Okay\Core\Security;

/**
 * Проверка того, что редирект остаётся в пределах текущего origin.
 *
 * Используется везде, где адрес перехода приходит из запроса.
 */
class SafeRedirect
{
    public static function isSameOrigin($url, $baseUrl)
    {
        if (!is_string($url) || $url === '') {
            return false;
        }

        // Двойное декодирование, чтобы %2f%2f и %252f%252f не проскочили как //.
        $decoded = rawurldecode(rawurldecode($url));

        if (preg_match('/[\x00-\x1f\x7f]/', $decoded)) {
            return false;
        }

        if (strpos($decoded, '\\') !== false) {
            return false;
        }

        if (strpos($decoded, '//') === 0) {
            return false;
        }

        if ($decoded[0] === '/') {
            return true;
        }

        $parsed = @parse_url($decoded);
        if ($parsed === false || empty($parsed['scheme']) || empty($parsed['host'])) {
            return false;
        }

        if (!in_array(strtolower($parsed['scheme']), ['http', 'https'], true)) {
            return false;
        }

        // Учётные данные в URL используются, чтобы замаскировать чужой хост:
        // http://shop.example@evil.com/ ведёт на evil.com.
        if (isset($parsed['user']) || isset($parsed['pass'])) {
            return false;
        }

        $base = @parse_url($baseUrl);
        if ($base === false || empty($base['host'])) {
            return false;
        }

        return strtolower($parsed['host']) === strtolower($base['host']);
    }
}
