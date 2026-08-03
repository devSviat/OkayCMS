<?php

namespace Okay\Core\Security;

/**
 * Лічильник невдалих спроб для ендпойнтів без сесії.
 *
 * Сховище файлове, а не в БД: схема лишається незмінною, а викликач такого
 * ендпойнта - чужий сервер, який не носить кук, тож сесія тут не працює.
 */
class AttemptLimiter
{
    private $dir;
    private $maxAttempts;
    private $windowSeconds;

    public function __construct($dir, $maxAttempts = 10, $windowSeconds = 300)
    {
        $this->dir = rtrim((string)$dir, '/');
        $this->maxAttempts = max(1, (int)$maxAttempts);
        $this->windowSeconds = max(1, (int)$windowSeconds);
    }

    public function tooManyAttempts($key)
    {
        return count($this->recentFailures($key)) >= $this->maxAttempts;
    }

    public function registerFailure($key)
    {
        $failures = $this->recentFailures($key);
        $failures[] = time();

        if (count($failures) > $this->maxAttempts) {
            $failures = array_slice($failures, -$this->maxAttempts);
        }

        $this->write($key, $failures);
    }

    public function reset($key)
    {
        $file = $this->fileFor($key);
        if ($file !== null && is_file($file)) {
            @unlink($file);
        }
    }

    private function recentFailures($key)
    {
        $file = $this->fileFor($key);
        if ($file === null || !is_file($file)) {
            return [];
        }

        $raw = @file_get_contents($file);
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $stored = json_decode($raw, true);
        if (!is_array($stored)) {
            return [];
        }

        $threshold = time() - $this->windowSeconds;
        $recent = [];
        foreach ($stored as $timestamp) {
            if (is_int($timestamp) && $timestamp > $threshold) {
                $recent[] = $timestamp;
            }
        }

        return $recent;
    }

    private function write($key, array $failures)
    {
        $file = $this->fileFor($key);
        if ($file === null) {
            return;
        }

        if (!is_dir($this->dir) && !@mkdir($this->dir, 0775, true) && !is_dir($this->dir)) {
            return;
        }

        @file_put_contents($file, json_encode(array_values($failures)), LOCK_EX);
    }

    /**
     * Ім'я файлу - хеш ключа: ключем може бути IP або довільний ідентифікатор
     * дії, і жоден з них не можна класти в шлях як є.
     */
    private function fileFor($key)
    {
        $key = (string)$key;
        if ($key === '' || $this->dir === '') {
            return null;
        }

        return $this->dir . '/' . hash('sha256', $key) . '.json';
    }
}
