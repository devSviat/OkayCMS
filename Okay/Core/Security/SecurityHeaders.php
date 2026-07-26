<?php

namespace Okay\Core\Security;

/**
 * Базові захисні заголовки HTML-відповіді.
 *
 * CSP сюди свідомо не входить: вона потребує інвентаризації інлайнових
 * скриптів теми й виноситься в окрему ітерацію.
 */
class SecurityHeaders
{
    /**
     * @return string[]
     */
    public static function defaults()
    {
        return [
            'X-Frame-Options: SAMEORIGIN',
            'X-Content-Type-Options: nosniff',
            'Referrer-Policy: strict-origin-when-cross-origin',
        ];
    }
}
