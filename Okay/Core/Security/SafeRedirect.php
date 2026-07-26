<?php

namespace Okay\Core\Security;

/**
 * Перевірка того, що редирект лишається в межах поточного origin.
 *
 * Використовується скрізь, де адреса переходу приходить із запиту.
 */
class SafeRedirect
{
    public static function isSameOrigin($url, $baseUrl)
    {
        if (!is_string($url) || $url === '') {
            return false;
        }

        // Подвійне декодування, щоб %2f%2f і %252f%252f не проскочили як //.
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

        // Облікові дані в URL використовують, щоб замаскувати чужий хост:
        // http://shop.example@evil.com/ веде на evil.com.
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
