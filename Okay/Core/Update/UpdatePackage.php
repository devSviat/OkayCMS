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
     * Каталоги, які належать інсталяції, а не ядру. Пакет не має права
     * заявляти в них жодного шляху — навіть цілком справжній пакет,
     * зібраний із помилкою в `release-manifest.json`.
     *
     * Порівняння регістронезалежне: на FS, що не розрізняє регістр,
     * `CONFIG/config.local.php` перезаписав би `config/config.local.php`.
     */
    private const OUTSIDE_CORE_PREFIXES = [
        'config/',
        'files/',
        'design/',
        'cache/',
        'compiled/',
        'vendor/',
        'Okay/log/',
        'backend/files/',
        'backend/design/',
    ];

    /**
     * Кореневі файли, яких оновлення не везе свідомо: `.htaccess` — головний
     * важіль безпеки периметра на класичному хостингу й найчастіше локально
     * дописаний, `robots.txt` — рішення власника магазину.
     */
    private const OUTSIDE_CORE_FILES = [
        '.htaccess',
        'robots.txt',
    ];

    /** Єдиний вендор модулів, який постачається разом із ядром. */
    private const CORE_MODULE_VENDOR = 'okaycms';

    /**
     * Виняток із `backend/design/`: сторінка оновлювача мусить оновлюватись
     * разом із ним, інакше правку в ній не доставити ніколи. Перелік живе
     * ТУТ, у коді інсталяції, а не в пакеті — інакше межу визначав би той,
     * від кого її й треба захищати.
     */
    private const CORE_OWNED_EXCEPTIONS = [
        'backend/design/html/core_updater.tpl',
    ];

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
     * Межа «ядро проти магазину» на боці ЗАСТОСУВАННЯ.
     *
     * `release-manifest.json` описує цю межу на боці збірки пакета — тобто її
     * тримає той, хто реліз збирає. Для інсталяції це означає, що єдиний
     * запобіжник від пакета, який заявив `design/`, `config/` чи чужий модуль,
     * лежить у чужому репозиторії. Тут та сама межа перевіряється ще раз,
     * власним кодом, до будь-яких змін на диску.
     *
     * Це deny-list, а не allow-list, і свідомо. Allow-list мусив би
     * дзеркалити перелік core-шляхів, а він росте з версіями: інсталяція на
     * 1.3.3 відмовила б оновленню, яке додає новий каталог ядра, і зламала б
     * сумісність уперед. Deny-list закриває те, що не може бути законним
     * НІКОЛИ, і не заважає ядру рости.
     *
     * Викликається на кроці `verify`, поруч з assertSafePaths() — до бекапу,
     * технічних робіт і підміни файлів. Порушення зупиняє прогін цілком, а не
     * пропускає окремий шлях: пакет, який заявив чуже, зібраний не так, як
     * задумано, і застосовувати з нього решту — здогадка.
     *
     * @param array<array-key, string> $manifestFiles відносний шлях => sha256
     * @throws \RuntimeException з переліком УСІХ порушень, а не першого
     */
    public static function assertPathsWithinCoreBoundary(array $manifestFiles): void
    {
        $violations = [];

        foreach (array_keys($manifestFiles) as $path) {
            $path = (string) $path;
            if (in_array($path, self::CORE_OWNED_EXCEPTIONS, true)) {
                continue;
            }

            $reason = self::boundaryViolationReason($path);
            if ($reason !== null) {
                $violations[] = $path . ' — ' . $reason;
            }
        }

        if ($violations !== []) {
            throw new \RuntimeException(
                "Пакет заявляє шляхи поза ядром — оновлення зупинено до будь-яких змін:\n"
                . implode("\n", $violations)
            );
        }
    }

    /** @return ?string причина відмови, або null якщо шлях у межах ядра */
    private static function boundaryViolationReason(string $path): ?string
    {
        $lower = mb_strtolower($path);

        foreach (self::OUTSIDE_CORE_FILES as $file) {
            if ($lower === mb_strtolower($file)) {
                return 'кореневий файл інсталяції, оновленням не постачається';
            }
        }

        foreach (self::OUTSIDE_CORE_PREFIXES as $prefix) {
            if (str_starts_with($lower, mb_strtolower($prefix))) {
                return 'каталог належить інсталяції, не ядру';
            }
        }

        // Модулі: разом із ядром їде лише вендор OkayCMS. Решта — сторонні
        // або власні модулі магазину, і оновлення ядра їх не чіпає.
        if (str_starts_with($lower, 'okay/modules/')) {
            $segments = explode('/', $lower);
            $vendor = $segments[2] ?? '';

            if ($vendor === '') {
                return 'шлях модуля без вендора';
            }

            if ($vendor !== self::CORE_MODULE_VENDOR) {
                return 'модуль вендора "' . $vendor . '" не постачається з ядром';
            }
        }

        return null;
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
