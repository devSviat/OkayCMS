<?php


namespace Okay\Core\Log;


use Monolog\Handler\RotatingFileHandler;

/**
 * Недоступний для запису лог не повинен валити запит.
 *
 * StreamHandler кидає виняток, коли не може відкрити файл, і нічого його не
 * ловить: сторінка віддає 500 замість того, щоб просто не залогувати рядок.
 * Права ламаються буденно - файл за добу створює той, хто написав перший, а
 * ./ok і php-fpm ходять під різними користувачами.
 *
 * Повідомлення не губиться: воно йде в error_log, тобто в stderr php-fpm.
 */
class SafeRotatingFileHandler extends RotatingFileHandler
{
    protected function write(array $record)
    {
        try {
            parent::write($record);
        } catch (\Throwable $e) {
            $original = isset($record['formatted']) ? $record['formatted'] : $record['message'];
            error_log('log write failed (' . $e->getMessage() . '); original message: ' . trim($original));
        }
    }
}
