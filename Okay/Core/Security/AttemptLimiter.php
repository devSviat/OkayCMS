<?php

namespace Okay\Core\Security;

use Psr\Log\LoggerInterface;

/**
 * Лічильник невдалих спроб для ендпойнтів без сесії.
 *
 * Сховище файлове, а не в БД: схема лишається незмінною, а викликач такого
 * ендпойнта - чужий сервер, який не носить кук, тож сесія тут не працює.
 */
class AttemptLimiter
{
    /** Файл лічильника, якого не чіпали довше, вже не містить жодної мітки в межах вікна. */
    public const PRUNE_AFTER_SECONDS = 7200;

    private $dir;
    private $maxAttempts;
    private $windowSeconds;
    private ?LoggerInterface $logger;

    public function __construct($dir, $maxAttempts = 10, $windowSeconds = 300, ?LoggerInterface $logger = null)
    {
        $this->dir = rtrim((string)$dir, '/');
        $this->maxAttempts = max(1, (int)$maxAttempts);
        $this->windowSeconds = max(1, (int)$windowSeconds);
        $this->logger = $logger;
    }

    /**
     * Той самий лічильник з іншими межами, наприклад для входу в адмінку.
     */
    public function withLimits(int $maxAttempts, int $windowSeconds): self
    {
        return new self($this->dir, $maxAttempts, $windowSeconds, $this->logger);
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
     * Сховище недоступне — спробу пропускаємо й пишемо помилку в лог. Закрити
     * натомість означало б, що тека з root-власником після `docker compose
     * exec` замикає вхід в адмінку всім, і виправити це можна лише з сервера.
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

    /**
     * Повертає останню зайняту спробу: успіх не має витрачати ліміт.
     */
    public function refund($key): void
    {
        $this->locked($key, function (array $recent) {
            array_pop($recent);

            return [null, $recent];
        }, null);
    }

    /**
     * Видаляє файли лічильників, яких давно не чіпали. Інакше кожна адреса й
     * кожен вигаданий логін лишають по файлу назавжди. Задача планувальника.
     *
     * @return int скільки видалено
     */
    public function prune(): int
    {
        $removed = 0;
        $threshold = time() - self::PRUNE_AFTER_SECONDS;
        foreach (glob($this->dir . '/*.json') ?: [] as $file) {
            if (@filemtime($file) < $threshold && @unlink($file)) {
                $removed++;
            }
        }

        return $removed;
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
        if ($file === null) {
            return $unavailable;
        }

        $handle = $this->ensureDir() ? @fopen($file, 'c+') : false;
        if ($handle === false) {
            $this->logger?->error('AttemptLimiter: сховище лічильників недоступне на запис, ліміт спроб не діє', ['dir' => $this->dir]);

            return $unavailable;
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                $this->logger?->error('AttemptLimiter: не вдалося заблокувати файл лічильника, ліміт спроб не діє', ['dir' => $this->dir]);

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
