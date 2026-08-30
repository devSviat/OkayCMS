<?php

namespace Okay\Modules\OkayCMS\CoreUpdater\Helpers;

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

    public static function enable(string $flagPath): string
    {
        $token = bin2hex(random_bytes(16));

        file_put_contents($flagPath, json_encode([
            'startedAt' => time(),
            'token' => $token,
        ]));

        return $token;
    }

    public static function disable(string $flagPath): void
    {
        if (is_file($flagPath)) {
            unlink($flagPath);
        }
    }

    public static function isActive(string $flagPath): bool
    {
        return is_file($flagPath);
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
