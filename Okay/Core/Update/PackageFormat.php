<?php

namespace Okay\Core\Update;

/**
 * Покоління пакета оновлення: що інсталяція мусить уміти, щоб прийняти пакет.
 *
 * Пакет перевіряє код інсталяції, тобто старий. Коли межа дозволеного
 * розширюється, старий код відхилив би новий пакет цілком. Тому покоління
 * закодоване в назві архіву: інсталяція не впізнає архів чужого покоління й
 * бере найновіший реліз, який уміє встановити. Оновившись, вона вже бачить
 * наступний. Так сайт на 1.3.9 спершу йде на 1.3.12 і лише потім на 1.4.0.
 *
 * 1 — без `backend/design/` (лише `core_updater*`); інсталяції до 1.3.12.
 * 2 — з `backend/design/`, крім `compiled/`; інсталяції з 1.3.12.
 */
final class PackageFormat
{
    public const int LEGACY = 1;

    /** Найновіше покоління, яке ця інсталяція вміє встановити. */
    public const int SUPPORTED = 2;

    /** Перше покоління отримало назву до появи поколінь, і назву міняти не можна: її шукають старі інсталяції. */
    public static function zipAssetName(string $version, int $format): string
    {
        if ($format < self::LEGACY) {
            throw new \InvalidArgumentException("Невідоме покоління пакета: {$format}");
        }

        return $format === self::LEGACY
            ? "okaycms-fork-v{$version}.zip"
            : "okaycms-fork-v{$version}-f{$format}.zip";
    }

    /** @return ?int покоління архіву цієї версії, або null, якщо назва не наша */
    public static function ofZipAssetName(string $name, string $version): ?int
    {
        if ($name === self::zipAssetName($version, self::LEGACY)) {
            return self::LEGACY;
        }

        $prefix = "okaycms-fork-v{$version}-f";
        if (!str_starts_with($name, $prefix) || !preg_match('/^([2-9]|[1-9]\d+)\.zip$/', substr($name, strlen($prefix)), $m)) {
            return null;
        }

        return (int) $m[1];
    }
}
