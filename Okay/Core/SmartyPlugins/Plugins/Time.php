<?php


namespace Okay\Core\SmartyPlugins\Plugins;


use Okay\Core\SmartyPlugins\Modifier;

class Time extends Modifier
{
    public function run($date, $format = null)
    {
        // Порожній last_activity менеджера доїжджав сюди як null: strtotime()
        // від PHP 8.1 на це сварить прямо в HTML, а date() отримував не число.
        // Розбір дати дзеркалить Date, щоб плагіни не розходились.
        if (is_numeric($date)) {
            $time = (int)$date;
        } elseif ($date === null) {
            $time = null; // date() читає null як "зараз" - давня поведінка.
        } elseif (($parsed = strtotime((string)$date)) !== false) {
            $time = $parsed;
        } else {
            return (string)$date;
        }

        return date(empty($format) ? 'H:i' : $format, $time);
    }
}