<?php

namespace Okay\Modules\Sviat\CoreUpdater\Helpers;

/**
 * Прапорець технічних робіт на час застосування оновлення ядра. Усі методи
 * статичні й приймають шлях прапорця явно — це навмисно, щоб index.php міг
 * перевірити його ще до DI/БД (клас недоступний з контейнером так рано).
 */
class MaintenanceMode
{
    public static function flagPath(string $rootDir): string
    {
        return $rootDir . '/config/.maintenance';
    }

    /**
     * Результат запису перевіряється обов'язково: мовчазний провал віддав би
     * валідний токен при відкритій вітрині — увесь apply пройшов би поверх
     * живого сайту, а відкат потім рапортував би успіх.
     */
    public static function enable(string $flagPath): string
    {
        $token = bin2hex(random_bytes(16));

        $payload = json_encode(['startedAt' => time(), 'token' => $token]);
        if (@file_put_contents($flagPath, $payload) === false) {
            throw new \RuntimeException("Не вдалося виставити прапорець технічних робіт: {$flagPath}");
        }

        return $token;
    }

    /** Провал unlink() мусить бути гучним: інакше сайт лишається закритим зі статусом «done». */
    public static function disable(string $flagPath): void
    {
        if (is_file($flagPath) && !@unlink($flagPath)) {
            throw new \RuntimeException("Не вдалося зняти прапорець технічних робіт: {$flagPath}");
        }
    }

    public static function isActive(string $flagPath): bool
    {
        return is_file($flagPath);
    }

    /**
     * index.php читає токен із суперглобалів, де PHP розбирає
     * `?core_updater_token[]=x` у масив — allowsRequest() навмисно лишає
     * строгий `?string`, тож межа коерції винесена сюди, окремо
     * тестованою, замість мовчазного ослаблення типу параметра.
     */
    public static function normalizeToken(mixed $raw): ?string
    {
        return is_string($raw) ? $raw : null;
    }

    /**
     * Битий JSON у прапорці трактується як активний режим без токен-обходу
     * (fail-closed) — краще зайвий 503, ніж витік вітрини під час апдейту.
     */
    public static function allowsRequest(string $flagPath, ?string $providedToken): bool
    {
        if (!self::isActive($flagPath)) {
            return true;
        }

        $contents = file_get_contents($flagPath);
        if ($contents === false) {
            return false;
        }

        $data = json_decode($contents, true);
        $storedToken = is_array($data) ? ($data['token'] ?? null) : null;

        if (!is_string($storedToken) || $storedToken === '' || !is_string($providedToken)) {
            return false;
        }

        return hash_equals($storedToken, $providedToken);
    }

    public static function renderPage(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="uk">
<head>
<meta charset="utf-8">
<title>Технічні роботи</title>
</head>
<body>
<h1>503 Сервіс тимчасово недоступний</h1>
<p>Триває оновлення. Спробуйте, будь ласка, за кілька хвилин.</p>
</body>
</html>
HTML;
    }
}
