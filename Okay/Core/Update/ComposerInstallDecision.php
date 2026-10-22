<?php

namespace Okay\Core\Update;

/**
 * Чи потрібен `composer install --no-dev` після встановлення пакета оновлення.
 *
 * Порівнюється не файл цілком, а лише те, від чого залежить дерево `vendor/` і
 * автозавантажувач у прод-режимі. Інакше бамп phpunit чи phpstan (це
 * `packages-dev`, на прод не їде) змушував кожну інсталяцію ганяти composer, а
 * на хостингу без composer робив реліз невстановлюваним узагалі.
 *
 * З `composer.lock` відкидаються `packages-dev` і `content-hash`: другий
 * рахується з усього `composer.json`, разом із dev-секціями. Щоб не загубити
 * прод-зміни `composer.json`, які в lock видно лише через той самий хеш
 * (`autoload` кореня, `config`, `scripts`), сам `composer.json` теж
 * порівнюється, без `require-dev` і `autoload-dev`.
 */
final class ComposerInstallDecision
{
    private const LOCK_DEV_KEYS = ['packages-dev', 'content-hash', '_readme'];

    private const JSON_DEV_KEYS = ['require-dev', 'autoload-dev'];

    /**
     * @param string $rootDir корінь сайту з поточними composer.lock/composer.json
     * @param string $payloadDir розпакований payload пакета оновлення
     */
    public static function isRequired(string $rootDir, string $payloadDir): bool
    {
        $rootDir = rtrim($rootDir, '/');
        $payloadDir = rtrim($payloadDir, '/');

        // Пакет без composer.lock залежностей не чіпає.
        $packageLock = self::read($payloadDir . '/composer.lock');
        if ($packageLock === null) {
            return false;
        }

        if (self::differs(self::read($rootDir . '/composer.lock'), $packageLock, self::LOCK_DEV_KEYS)) {
            return true;
        }

        $packageJson = self::read($payloadDir . '/composer.json');

        return $packageJson !== null
            && self::differs(self::read($rootDir . '/composer.json'), $packageJson, self::JSON_DEV_KEYS);
    }

    /** @param list<string> $devKeys */
    private static function differs(?string $current, string $package, array $devKeys): bool
    {
        if ($current === null) {
            return true;
        }

        $currentData = json_decode($current, true);
        $packageData = json_decode($package, true);

        // Нерозбірний файл — без здогадок: та сама відповідь, що й побайтове порівняння.
        if (!is_array($currentData) || !is_array($packageData)) {
            return $current !== $package;
        }

        return self::canonical($currentData, $devKeys) !== self::canonical($packageData, $devKeys);
    }

    /** @param list<string> $devKeys */
    private static function canonical(array $data, array $devKeys): string
    {
        foreach ($devKeys as $key) {
            unset($data[$key]);
        }

        return json_encode(self::sortKeys($data), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Порядок ключів об'єктів у JSON нічого не значить, порядок елементів
     * списку — значить (packages, repositories), тож сортуються лише перші.
     */
    private static function sortKeys(array $data): array
    {
        if (!array_is_list($data)) {
            ksort($data);
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::sortKeys($value);
            }
        }

        return $data;
    }

    private static function read(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }

        $content = file_get_contents($path);

        return $content === false ? null : $content;
    }
}
