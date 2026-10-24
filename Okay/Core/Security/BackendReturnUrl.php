<?php

namespace Okay\Core\Security;

/**
 * Параметр ?return= в адмінці: кнопка «Повернутися» після збереження.
 *
 * Шаблони ядра й модулів друкують його як href="{$smarty.get.return}" без
 * екранування, а автоекранування в адмінці немає. Тож значення чиститься
 * один раз на вході, а не в кожному з ~40 шаблонів.
 */
class BackendReturnUrl
{
    /** Символи, що виводять значення з атрибута в лапках або в розмітку. */
    private const array ATTRIBUTE_BREAKERS = [
        '"' => '%22',
        "'" => '%27',
        '<' => '%3C',
        '>' => '%3E',
        '`' => '%60',
    ];

    /**
     * @return string|null адреса в межах сайту, безпечна для href; null — параметр треба прибрати
     */
    public static function sanitize(mixed $value, string $rootUrl): ?string
    {
        if (!is_string($value) || !SafeRedirect::isSameOrigin($value, $rootUrl)) {
            return null;
        }

        return strtr($value, self::ATTRIBUTE_BREAKERS);
    }
}
