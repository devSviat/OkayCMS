<?php

namespace Okay\Core\Import;

/**
 * Значення властивостей, зіпсовані CSV з escape '\\' (до devSviat/OkayCMS-Fork#347):
 * слеш перед лапкою екранував саме лапку, тож кожен цикл експорт → імпорт
 * лишав «\» і множив лапки — `\""PLUS\""`, `""""""PLUS`, `PULSE"""""""`.
 */
final class CsvQuoteDamage
{
    /** Підпис саме цього пошкодження. Слеш без лапки поруч (`KRSC96\I`) — дані джерела, не CSV. */
    public static function isDamaged(string $value): bool
    {
        return str_contains($value, '\\"') || str_contains($value, '""');
    }

    public static function repair(string $value): string
    {
        if (!self::isDamaged($value)) {
            return $value;
        }

        // У зіпсованому значенні слеш без лапки після нього — це з'їдена відкривальна лапка:
        // `\PLUS\"""` було `"PLUS"`. Серія лапок, зі слешем чи без, — одна лапка.
        $repaired = preg_replace('/\\\\"*|"+/', '"', $value);

        // Непарна кількість — лапка-хвіст без пари (`PULSE EN 110.B"""""""`).
        if (substr_count($repaired, '"') % 2 === 1 && str_ends_with($repaired, '"')) {
            $repaired = substr($repaired, 0, -1);
        }

        return trim($repaired);
    }
}
