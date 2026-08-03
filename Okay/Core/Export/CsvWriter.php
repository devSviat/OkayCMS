<?php

namespace Okay\Core\Export;

/**
 * Спільний запис CSV для backend-експортів.
 *
 * Два завдання. Перше — значення, які Excel і LibreOffice трактують як
 * формулу: рядок, що починається з =, +, - або @, виконується при відкритті
 * файла. У експорт потрапляють імена, коментарі й адреси, які вводить
 * покупець, тож формула туди потрапляє ззовні.
 *
 * Друге — кодування. Файл накопичується частинами між AJAX-сторінками, тому
 * BOM пишеться один раз, разом із заголовком, а не домішується наприкінці.
 */
class CsvWriter
{
    /**
     * UTF-8 BOM. Без нього Excel під Windows читає файл в ANSI і кирилиця
     * розсипається; LibreOffice і Google Sheets розуміють обидва варіанти.
     */
    const BOM = "\xEF\xBB\xBF";

    /**
     * Символи, з яких Excel починає розбір формули. Табуляція і повернення
     * каретки — теж: ними можна відсунути початок значення так, щоб
     * формула лишилась формулою.
     */
    const FORMULA_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * Пише заголовок і BOM. Викликати лише для першої сторінки експорту.
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

    /**
     * Апостроф попереду — те, чим Excel позначає "це текст". Він видимий у
     * рядку формул, але не в комірці, і значення лишається читабельним.
     */
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
