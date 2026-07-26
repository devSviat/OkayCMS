<?php

namespace Okay\Core\Security;

/**
 * Базовые защитные заголовки HTML-ответа.
 *
 * CSP сюда сознательно не входит: она требует инвентаризации инлайновых
 * скриптов темы и выносится в отдельную итерацию.
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
