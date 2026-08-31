<?php

namespace Okay\Core\Update;

/**
 * Верифікація пакета оновлення ядра (спек §5, §8 крок 4, §11) — усі методи
 * pure, шляхи інжектяться, ніякої мережі/БД. Довіра до вмісту пакета
 * будується виключно на `checksums.txt`/`manifest.json`: усе, чого там
 * немає, для апдейтера не існує, навіть якщо фізично лежить у дереві.
 */
class UpdatePackage
{
    /**
     * Розбирає `checksums.txt` у форматі `{sha256}  {name}\n`, як пише
     * `PackageBuilder::build()`.
     *
     * @return array<string, string> назва файлу => sha256
     */
    public static function parseChecksums(string $checksumsTxt): array
    {
        $result = [];

        foreach (preg_split('/\r\n|\r|\n/', trim($checksumsTxt)) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (!preg_match('/^([0-9a-f]{64})\s+(\S.*)$/', $line, $matches)) {
                throw new \RuntimeException("Непридатний рядок у checksums.txt: \"{$line}\".");
            }

            $result[$matches[2]] = $matches[1];
        }

        return $result;
    }

    /**
     * @param array<string, string> $checksums назва файлу => sha256, з parseChecksums()
     */
    public static function verifyArchiveHash(string $zipPath, array $checksums): void
    {
        $name = basename($zipPath);
        $expected = $checksums[$name] ?? null;
        if (!is_string($expected) || $expected === '') {
            throw new \RuntimeException("У checksums.txt немає запису для \"{$name}\".");
        }

        $actual = hash_file('sha256', $zipPath);
        if ($actual === false) {
            throw new \RuntimeException("Не вдалося обчислити контрольну суму архіву: {$zipPath}");
        }

        if (!hash_equals($expected, $actual)) {
            throw new \RuntimeException(
                "Контрольна сума архіву не збігається: очікувалось {$expected}, отримано {$actual}."
            );
        }
    }

    /**
     * Перевіряє КОЖЕН файл із manifest.json проти розпакованого дерева.
     * Будь-яка розбіжність чи відсутність файлу зупиняє перевірку одразу —
     * часткового "ок" не буває. Файли, присутні в дереві, але не заявлені
     * в manifest, тут навіть не розглядаються (ігноруються навмисно).
     *
     * Порожній manifest — це не "нічого перевіряти", а ознака битого/
     * обрізаного білда: він самоузгоджено пройде будь-яку перевірку
     * checksums і мовчки "оновить" нуль файлів, тож тут це явний фейл.
     *
     * @param array<string, mixed> $manifestFiles відносний шлях => sha256;
     *     значення типізовано широко — сирий, ще неперевірений manifest.json
     *     може містити будь-що.
     * @return list<string> перевірені відносні шляхи, у порядку manifest
     */
    public static function verifyExtractedFiles(string $extractedDir, array $manifestFiles): array
    {
        if ($manifestFiles === []) {
            throw new \RuntimeException(
                'manifest.json не містить жодного файлу — пакет схожий на битий білд, перевірку зупинено.'
            );
        }

        $verified = [];

        foreach ($manifestFiles as $relativePath => $expectedHash) {
            if (!is_string($expectedHash) || $expectedHash === '') {
                throw new \RuntimeException(
                    "Некоректний sha256 у manifest.json для файлу {$relativePath}."
                );
            }

            $fullPath = rtrim($extractedDir, '/') . '/' . $relativePath;

            if (!is_file($fullPath)) {
                throw new \RuntimeException(
                    "У пакеті відсутній файл, заявлений у manifest.json: {$relativePath}"
                );
            }

            $actualHash = hash_file('sha256', $fullPath);
            if ($actualHash === false || !hash_equals($expectedHash, $actualHash)) {
                throw new \RuntimeException(
                    "Контрольна сума файлу не збігається з manifest.json: {$relativePath}"
                );
            }

            $verified[] = $relativePath;
        }

        return $verified;
    }

    /**
     * Захист від виходу за межі кореня (спек §5, defense-in-depth):
     * жоден шлях із manifest.json не може бути абсолютним, містити `..`
     * як окремий сегмент чи використовувати `\` як роздільник.
     *
     * Порожній `$manifestFiles` тут навмисно проходить без винятку — метод
     * перевіряє шляхи, яких немає, а не факт "порожнього пакета" (це
     * робить verifyExtractedFiles()).
     *
     * @param array<array-key, string> $manifestFiles відносний шлях => sha256 —
     *     ключі типізовані широко: json_decode() перетворює числові рядки-ключі
     *     на int, і саме такий шлях має впасти в перевірку, а не пройти повз неї.
     */
    public static function assertSafePaths(array $manifestFiles): void
    {
        foreach (array_keys($manifestFiles) as $path) {
            if (!is_string($path) || $path === '') {
                throw new \RuntimeException('Порожній або некоректний шлях у manifest.json.');
            }

            if (str_contains($path, '\\')) {
                throw new \RuntimeException("Шлях у manifest.json використовує заборонений роздільник \"\\\": {$path}");
            }

            if (str_starts_with($path, '/') || preg_match('#^[A-Za-z]:/#', $path)) {
                throw new \RuntimeException("Абсолютний шлях у manifest.json не допускається: {$path}");
            }

            foreach (explode('/', $path) as $segment) {
                if ($segment === '..') {
                    throw new \RuntimeException("Шлях у manifest.json виходить за межі кореня: {$path}");
                }
            }
        }
    }

    /**
     * Читає `version.json` пакета. Поля upstreamBase/minPhp/
     * requiresMigrations/releasedAt розбираються через
     * ReleaseFeed::parseVersionMeta() (той самий контракт, що й C1) —
     * forkVersion додається окремо, бо в контексті C1 (список релізів)
     * він береться з тегу, а тут іншого джерела, крім самого файлу, немає.
     *
     * @return array{forkVersion: string, upstreamBase: ?string, minPhp: ?string, requiresMigrations: ?bool, releasedAt: ?string}
     */
    public static function readVersionMeta(string $extractedDir): array
    {
        $path = rtrim($extractedDir, '/') . '/version.json';

        $json = is_file($path) ? file_get_contents($path) : false;
        if ($json === false) {
            throw new \RuntimeException("У пакеті відсутній version.json: {$path}");
        }

        $meta = ReleaseFeed::parseVersionMeta($json);
        if ($meta === null) {
            throw new \RuntimeException("version.json пошкоджений або не є об'єктом: {$path}");
        }

        $data = json_decode($json, true);
        $forkVersion = is_array($data) && is_string($data['forkVersion'] ?? null) ? $data['forkVersion'] : null;
        if ($forkVersion === null || $forkVersion === '') {
            throw new \RuntimeException("version.json не містить коректного forkVersion: {$path}");
        }

        return ['forkVersion' => $forkVersion] + $meta;
    }

    /**
     * PHP-compatibility gate і downgrade guard (спек §8 крок 5, §11) — до
     * будь-яких змін у файловій системі чи БД. Повідомлення людські й
     * українською: побачить їх адмін у CoreUpdater UI.
     *
     * @param array<string, mixed> $versionMeta довільний масив, а не гарантовано
     *     readVersionMeta() — forkVersion/minPhp перевіряються тут самі, не лише
     *     покладаючись на типи виклику.
     * @param bool $allowSameVersion послаблює downgrade guard до `>=` для
     *     єдиного випадку — доїзду обірваного посеред apply прогону на ТУ САМУ
     *     версію. Там Config.php на диску вже новий, тож строгий `>` відмовив
     *     би саме в тій дії, яка й лікує стан; повторне застосування безпечне
     *     (файли ідемпотентні через rename, міграції прикриті трекером).
     *     Downgrade лишається забороненим і з прапорцем.
     */
    public static function assertInstallable(
        array $versionMeta,
        string $installedVersion,
        string $phpVersion,
        bool $allowSameVersion = false
    ): void {
        $forkVersion = $versionMeta['forkVersion'] ?? null;
        $minPhp = $versionMeta['minPhp'] ?? null;

        if (is_string($minPhp) && $minPhp !== '' && version_compare($phpVersion, $minPhp, '<')) {
            throw new \RuntimeException(
                "Цей реліз потребує PHP не нижче {$minPhp}, а на сервері встановлено {$phpVersion}. "
                . 'Оновіть PHP перед встановленням оновлення.'
            );
        }

        $operator = $allowSameVersion ? '>=' : '>';
        if (!is_string($forkVersion) || $forkVersion === '' || !version_compare($forkVersion, $installedVersion, $operator)) {
            throw new \RuntimeException(
                "Версія пакета ({$forkVersion}) не новіша за встановлену ({$installedVersion}) — "
                . 'оновлення не буде застосовано.'
            );
        }
    }
}
