<?php

namespace Okay\Core\Update;

/**
 * Розбір GitHub Releases JSON і вибір найновішого валідного fork-релізу.
 * Без залежностей: не звертається до БД, DI чи мережі.
 */
class ReleaseFeed
{
    private const string TAG_PATTERN = '#^okaycms-fork/v(\d+\.\d+\.\d+)$#';

    /**
     * Довіряємо asset-посиланням лише з нашого репозиторію релізів —
     * інакше зловмисний конкурентний release ('assets' підробити не можна,
     * а от чужий 'browser_download_url' у власному релізі — можна).
     *
     * Стара адреса — з часів до перейменування devSviat/OkayCMS → devSviat/OkayCMS-Fork
     * (23.10.2026): знімок релізів, збережений до нього, ще містить такі посилання.
     * Прибрати, коли на всіх сайтах стоїть версія, новіша за 1.3.9.
     */
    public const TRUSTED_ASSET_URL_PREFIXES = [
        'https://github.com/devSviat/OkayCMS/releases/download/',
        'https://github.com/devSviat/OkayCMS-Fork/releases/download/',
    ];

    public static function isTrustedAssetUrl($url): bool
    {
        if (!is_string($url)) {
            return false;
        }
        foreach (self::TRUSTED_ASSET_URL_PREFIXES as $prefix) {
            if (str_starts_with($url, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Реліз, на який ця інсталяція оновлюється наступним.
     *
     * Спершу — найновіший патч своєї гілки (major.minor). Лише з останнього
     * патча — найновіший патч наступної гілки, що існує, і так крок за
     * кроком. Так кожен сайт проходить останній патч кожної гілки, і туди
     * можна класти перехідне: розширення межі, нове покоління пакета.
     * Без $installed, або коли новішого немає, — просто найновіший.
     *
     * @return array{forkVersion: string, tag: string, publishedAt: ?string, notesUrl: ?string, notesBody: ?string, assets: array{zip: string, versionJson: string, checksums: string, packageFormat: int}}|null
     */
    public static function parseLatest(string $releasesJson, ?string $installed = null): ?array
    {
        $releases = json_decode($releasesJson, true);
        if (!is_array($releases)) {
            return null;
        }

        $candidates = [];
        foreach ($releases as $release) {
            $candidate = self::toCandidate($release);
            if ($candidate !== null) {
                $candidates[] = $candidate;
            }
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, static fn (array $a, array $b): int =>
            version_compare($b['forkVersion'], $a['forkVersion']));

        if ($installed === null) {
            return $candidates[0];
        }

        $newer = array_values(array_filter(
            $candidates,
            static fn (array $c): bool => self::isNewerThanInstalled($c['forkVersion'], $installed)
        ));
        if ($newer === []) {
            return $candidates[0];
        }

        // Гілка найстаршого з новіших релізів — своя, якщо в ній є новіший
        // патч, інакше найближча новіша. З неї беремо найновіший реліз
        // (список відсортовано за спаданням).
        $nextLine = self::line($newer[count($newer) - 1]['forkVersion']);
        foreach ($newer as $candidate) {
            if (self::line($candidate['forkVersion']) === $nextLine) {
                return $candidate;
            }
        }

        return null;
    }

    /** Гілка версії — major.minor: "1.3.12" → "1.3". */
    private static function line(string $version): string
    {
        $parts = explode('.', $version);

        return $parts[0] . '.' . ($parts[1] ?? '0');
    }

    public static function isNewerThanInstalled(string $candidate, string $installed): bool
    {
        return version_compare($candidate, $installed, '>');
    }

    /**
     * Чи вибрано `latest` знімка для цієї встановленої версії. `latestFor`
     * пишеться лише разом із вибором із повного списку релізів: `installed`
     * для цього не годиться, його переписують і помилка, і 304.
     *
     * @param array<string, mixed> $snapshot
     */
    public static function isChosenFor(array $snapshot, string $installed): bool
    {
        return ($snapshot['latestFor'] ?? null) === $installed;
    }

    public static function isSnapshotFresh(array $snapshot, int $nowTs, int $ttlSeconds): bool
    {
        $checkedAt = $snapshot['checkedAt'] ?? null;
        if (!is_int($checkedAt)) {
            return false;
        }

        return $checkedAt + $ttlSeconds > $nowTs;
    }

    /**
     * Оновлює installed/updateAvailable в снапшоті поточною версією форку —
     * ці два поля не можна віддавати «як застигли на момент check()»,
     * інакше щойно застосоване оновлення й далі показує стару картину.
     *
     * @param array<string, mixed> $snapshot
     * @return array<string, mixed>
     */
    public static function refreshInstalled(array $snapshot, string $installed): array
    {
        $snapshot['installed'] = $installed;

        $latestVersion = $snapshot['latest']['forkVersion'] ?? null;
        $snapshot['updateAvailable'] = is_string($latestVersion)
            && self::isNewerThanInstalled($latestVersion, $installed);

        return $snapshot;
    }

    /**
     * Розбір version.json — метадані релізу поза списком GitHub Releases
     * (мінімальна версія PHP, чи потрібні міграції тощо). Кожне поле
     * валідується окремо: часткові чи биті дані одних ключів не мають
     * ламати решту.
     *
     * @return array{upstreamBase: ?string, minPhp: ?string, requiresMigrations: ?bool, releasedAt: ?string, packageFormat: ?int, requiresFrom: ?string}|null
     */
    public static function parseVersionMeta(string $json): ?array
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return null;
        }

        return [
            'upstreamBase' => is_string($data['upstreamBase'] ?? null) ? $data['upstreamBase'] : null,
            'minPhp' => is_string($data['minPhp'] ?? null) ? $data['minPhp'] : null,
            'requiresMigrations' => is_bool($data['requiresMigrations'] ?? null) ? $data['requiresMigrations'] : null,
            'releasedAt' => is_string($data['releasedAt'] ?? null) ? $data['releasedAt'] : null,
            'packageFormat' => is_int($data['packageFormat'] ?? null) && $data['packageFormat'] >= PackageFormat::LEGACY ? $data['packageFormat'] : null,
            'requiresFrom' => is_string($data['requiresFrom'] ?? null) && preg_match('/^\d+\.\d+\.\d+$/', $data['requiresFrom']) ? $data['requiresFrom'] : null,
        ];
    }

    private static function toCandidate(mixed $release): ?array
    {
        if (!is_array($release)) {
            return null;
        }

        $tag = $release['tag_name'] ?? null;
        if (!is_string($tag) || !preg_match(self::TAG_PATTERN, $tag, $matches)) {
            return null;
        }

        if (!empty($release['draft']) || !empty($release['prerelease'])) {
            return null;
        }

        $version = $matches[1];
        $assets = self::mapAssets(is_array($release['assets'] ?? null) ? $release['assets'] : [], $version);
        if ($assets === null) {
            return null;
        }

        $publishedAt = $release['published_at'] ?? null;
        $notesUrl = $release['html_url'] ?? null;
        $notesBody = $release['body'] ?? null;

        return [
            'forkVersion' => $version,
            'tag' => $tag,
            'publishedAt' => is_string($publishedAt) ? $publishedAt : null,
            'notesUrl' => is_string($notesUrl) ? $notesUrl : null,
            'notesBody' => is_string($notesBody) ? self::plainNotes($notesBody) : null,
            'assets' => $assets,
        ];
    }

    /**
     * Нотатки релізу — markdown, а показуються в адмінці як текст: без чистки
     * читач бачив «## What's Changed» і «**Full Changelog**» як сміття.
     * Повноцінний рендер markdown тут зайвий — прибираємо лише розмітку,
     * яка найчастіше трапляється в автогенерованих нотатках GitHub.
     */
    public static function plainNotes(string $body): string
    {
        $body = preg_replace('/^#{1,6}\s+/m', '', $body);          // заголовки
        $body = preg_replace('/\*\*(.+?)\*\*/s', '$1', $body);     // жирний
        $body = preg_replace('/\[(.+?)\]\((.+?)\)/', '$1', $body); // markdown-посилання → текст

        // Хвіст автогенерації GitHub: «by @author in https://…/pull/123».
        // Авторство й номер PR читачеві адмінки нічого не кажуть, а довгі
        // URL ламали рядки й перетворювали список на кашу.
        $body = preg_replace('/\s+by\s+@[\w-]+(\s+in\s+\S+)?/u', '', $body);

        // Рядок «Full Changelog: …» дублює посилання «Переглянути зміни».
        $body = preg_replace('/^\s*Full Changelog:.*$/mi', '', $body);

        $body = preg_replace('/^\s*[-*]\s+/m', '• ', $body);       // марковані списки
        $body = preg_replace('/\n{3,}/', "\n\n", $body);           // зайві порожні рядки

        return trim($body);
    }

    /**
     * Архів береться найновішого покоління, яке ця інсталяція вміє встановити
     * (PackageFormat). Реліз лише з архівом новішого покоління пропускається:
     * так старий сайт іде на проміжну версію, а не на пакет, який відхилить.
     *
     * @return array{zip: string, versionJson: string, checksums: string, packageFormat: int}|null
     */
    private static function mapAssets(array $assets, string $version): ?array
    {
        $map = [];
        $zipFormat = 0;

        foreach ($assets as $asset) {
            if (!is_array($asset) || !isset($asset['name'], $asset['browser_download_url'])) {
                continue;
            }

            $url = $asset['browser_download_url'];
            if (!self::isTrustedAssetUrl($url) || !is_string($asset['name'])) {
                continue;
            }

            $format = PackageFormat::ofZipAssetName($asset['name'], $version);
            if ($format !== null) {
                if ($format <= PackageFormat::SUPPORTED && $format > $zipFormat) {
                    $map['zip'] = $url;
                    $zipFormat = $format;
                }
                continue;
            }

            $key = match ($asset['name']) {
                'version.json' => 'versionJson',
                'checksums.txt' => 'checksums',
                default => null,
            };
            if ($key !== null) {
                $map[$key] = $url;
            }
        }

        if (!isset($map['zip'], $map['versionJson'], $map['checksums'])) {
            return null;
        }

        return [
            'zip' => $map['zip'],
            'versionJson' => $map['versionJson'],
            'checksums' => $map['checksums'],
            'packageFormat' => $zipFormat,
        ];
    }
}
