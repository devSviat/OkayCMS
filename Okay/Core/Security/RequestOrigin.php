<?php

namespace Okay\Core\Security;

/**
 * Звірка походження мутуючого запиту.
 *
 * Другий рубіж поруч із CustomerCsrfToken: токен доводить, що форму видали ми,
 * а походження — що її відправили з нашої сторінки. Токен можна загубити разом
 * із сесією, заголовок від сторонньої сторінки підробити з JS не можна.
 *
 * На відміну від SafeRedirect::isSameOrigin(), яка звіряє лише хост, тут
 * потрібен повний збіг схеми, хоста й порту: http://shop і https://shop —
 * різні origin.
 */
class RequestOrigin
{
    /** Порти, які в origin не пишуться */
    private const DEFAULT_PORTS = ['http' => 80, 'https' => 443];

    /**
     * @param string|null $origin  заголовок Origin
     * @param string|null $referer заголовок Referer, запасний варіант
     * @param string      $siteUrl адреса магазину, звідки береться очікуваний origin
     * @return bool
     */
    public static function matchesSite($origin, $referer, $siteUrl)
    {
        $expected = self::normalize($siteUrl);
        if ($expected === null) {
            return false;
        }

        // Origin виграє: браузер ставить його сам, а Referer ріжуть проксі
        // й розширення приватності.
        foreach ([$origin, $referer] as $candidate) {
            if (!is_string($candidate) || $candidate === '') {
                continue;
            }

            // Пісочниця в iframe і частина редиректів шлють рядок "null".
            // Це не наш origin, і запасний варіант тут не рятує.
            if (strtolower($candidate) === 'null') {
                return false;
            }

            return self::normalize($candidate) === $expected;
        }

        // Жодного доказу походження - жодного проходу.
        return false;
    }

    /**
     * @param string $url
     * @return string|null scheme://host[:port] або null, якщо розібрати не вдалось
     */
    private static function normalize($url)
    {
        if ($url === '') {
            return null;
        }

        $parsed = @parse_url($url);
        if ($parsed === false || empty($parsed['scheme']) || empty($parsed['host'])) {
            return null;
        }

        $scheme = strtolower($parsed['scheme']);
        if (!isset(self::DEFAULT_PORTS[$scheme])) {
            return null;
        }

        $normalized = $scheme . '://' . strtolower($parsed['host']);

        if (isset($parsed['port']) && (int)$parsed['port'] !== self::DEFAULT_PORTS[$scheme]) {
            $normalized .= ':' . (int)$parsed['port'];
        }

        return $normalized;
    }
}
