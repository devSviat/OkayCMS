<?php

namespace Okay\Core\Export;

/**
 * Спільний запис CSV для backend-експортів: нейтралізує формули і тримає
 * файл у UTF-8.
 *
 * Значення, що починається з =, +, - або @, Excel виконує при відкритті, а в
 * експорт потрапляють імена, коментарі й адреси, які вводить покупець.
 */
class CsvWriter
{
    /** Без BOM Excel під Windows читає файл в ANSI і кирилиця розсипається. */
    const BOM = "\xEF\xBB\xBF";

    /** Табуляція і CR теж: ними відсувають початок значення, лишаючи формулу. */
    const FORMULA_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * Викликати лише для першої сторінки: файл дописується між AJAX-запитами.
     *
     * @param resource $handle
     * @param array<int|string, mixed> $columnNames
     */
    public static function putHeader($handle, array $columnNames, $delimiter = ';')
    {
        fwrite($handle, self::BOM);
        self::putRow($handle, $columnNames, $delimiter);
    }

    /**
     * @param resource $handle
     * @param array<int|string, mixed> $values
     */
    public static function putRow($handle, array $values, $delimiter = ';')
    {
        fputcsv($handle, self::escape($values), $delimiter, '"', '\\');
    }

    /**
     * @param array<int|string, mixed> $values
     * @return string[]
     */
    public static function escape(array $values)
    {
        return array_map([self::class, 'escapeValue'], $values);
    }

    /** Апостроф видимий у рядку формул, але не в комірці. */
    public static function escapeValue($value)
    {
        $value = (string)$value;

        if ($value === '') {
            return $value;
        }

        if (in_array($value[0], self::FORMULA_PREFIXES, true)) {
            return "'" . $value;
        }

        return $value;
    }
}
