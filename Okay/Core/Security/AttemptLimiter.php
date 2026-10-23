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

    /**
     * Той самий лічильник з іншими межами, наприклад для входу в адмінку.
     */
    public function withLimits(int $maxAttempts, int $windowSeconds): self
    {
        return new self($this->dir, $maxAttempts, $windowSeconds);
    }

    public function tooManyAttempts($key)
    {
        return count($this->recentFailures($key)) >= $this->maxAttempts;
    }

    /**
     * Займає спробу, якщо ліміт ще не вичерпано.
     *
     * Перевірка й запис ідуть під одним flock. Окремі tooManyAttempts() і
     * registerFailure() паралельні запити проходять разом: усі читають той
     * самий лічильник, і пачка з N одночасних спроб минає ліміт.
     *
     * Сховище недоступне — спробу пропускаємо, як і registerFailure(): без
     * запису в cache/ сайт однаково не працює, а вхід лишився б закритим.
     *
     * @return bool false — ліміт вичерпано, спробу не зараховано
     */
    public function attempt($key): bool
    {
        return $this->locked($key, function (array $recent) {
            if (count($recent) >= $this->maxAttempts) {
                return [false, null];
            }
            $recent[] = time();

            return [true, $recent];
        }, true);
    }

    public function registerFailure($key)
    {
        $this->locked($key, function (array $recent) {
            $recent[] = time();

            return [null, $recent];
        }, null);
    }

    public function reset($key)
    {
        $file = $this->fileFor($key);
        if ($file !== null && is_file($file)) {
            @unlink($file);
        }
    }

    /**
     * @param callable(int[]): array{0: mixed, 1: ?int[]} $change повертає
     *        [результат, нові мітки або null — не писати]
     * @param mixed $unavailable результат, коли сховище недоступне
     * @return mixed
     */
    private function locked($key, callable $change, $unavailable)
    {
        $file = $this->fileFor($key);
        if ($file === null || !$this->ensureDir()) {
            return $unavailable;
        }

        $handle = @fopen($file, 'c+');
        if ($handle === false) {
            return $unavailable;
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                return $unavailable;
            }

            [$result, $timestamps] = $change($this->recentFrom((string)stream_get_contents($handle)));

            if ($timestamps !== null) {
                $timestamps = array_slice(array_values($timestamps), -$this->maxAttempts);
                ftruncate($handle, 0);
                rewind($handle);
                fwrite($handle, (string)json_encode($timestamps));
                fflush($handle);
            }

            return $result;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function recentFailures($key)
    {
        $file = $this->fileFor($key);
        if ($file === null || !is_file($file)) {
            return [];
        }

        $raw = @file_get_contents($file);

        return is_string($raw) ? $this->recentFrom($raw) : [];
    }

    /** @return int[] мітки в межах вікна */
    private function recentFrom(string $raw): array
    {
        if ($raw === '') {
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

    private function ensureDir(): bool
    {
        return is_dir($this->dir) || @mkdir($this->dir, 0775, true) || is_dir($this->dir);
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
