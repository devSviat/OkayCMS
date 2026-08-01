<?php


namespace Okay\Core\Log;


use Monolog\Handler\RotatingFileHandler;
use Monolog\LogRecord;

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
    protected function write(LogRecord $record): void
    {
        try {
            parent::write($record);
        } catch (\Throwable $e) {
            // У Monolog 3 запис - обʼєкт LogRecord, а не масив; formatted лишається
            // порожнім, поки хендлер не дійшов до форматера.
            $original = !empty($record->formatted) ? (string)$record->formatted : $record->message;
            error_log('log write failed (' . $e->getMessage() . '); original message: ' . trim($original));
        }
    }
}
