<?php

namespace Okay\Core\Security;

/**
 * Зведення імені файлу чи каталогу із запиту до одного безпечного сегмента.
 *
 * Адмінські редактори теми (зображення, шаблони, скрипти, самі теми) склеюють
 * значення з запиту з каталогом теми. Без цього відсіву будь-який "../" у
 * значенні виводив unlink()/rename()/move_uploaded_file() за межі design/.
 */
class SafeFileName
{
    /**
     * Базове ім'я файлу: без каталогів, схем, NUL-байтів і "..".
     *
     * Повертає '' для всього, що не можна безпечно використати — код, що
     * викликає, зобов'язаний вважати '' відмовою.
     *
     * @param mixed $name
     * @return string
     */
    public static function basename($name)
    {
        if (!is_string($name) || $name === '') {
            return '';
        }

        if (strpos($name, "\0") !== false) {
            return '';
        }

        // basename() не вважає зворотний слеш роздільником на Linux,
        // тому нормалізуємо його самі.
        $name = str_replace('\\', '/', $name);
        $name = basename($name);

        // Крайні крапки прибираємо, щоб не лишилось "." чи "..".
        $name = trim($name, '.');

        return $name;
    }

    /**
     * Ім'я теми — один сегмент усередині design/, тільки [a-zA-Z0-9-_].
     *
     * @param mixed $name
     * @return string
     */
    public static function themeName($name)
    {
        if (!is_string($name)) {
            return '';
        }

        return preg_replace("/[^a-zA-Z0-9\-_]/", "", $name);
    }
}
