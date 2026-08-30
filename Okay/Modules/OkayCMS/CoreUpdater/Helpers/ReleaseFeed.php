<?php

namespace Okay\Modules\OkayCMS\CoreUpdater\Helpers;

/**
 * Розбір GitHub Releases JSON і вибір найновішого валідного fork-релізу.
 * Без залежностей: не звертається до БД, DI чи мережі.
 */
class ReleaseFeed
{
    private const TAG_PATTERN = '#^okaycms-fork/v(\d+\.\d+\.\d+)$#';

    /**
     * @return array{forkVersion: string, tag: string, publishedAt: ?string, notesUrl: ?string, assets: array{zip: string, versionJson: string, checksums: string}}|null
     */
    public static function parseLatest(string $releasesJson): ?array
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

        return $candidates[0];
    }

    public static function isNewerThanInstalled(string $candidate, string $installed): bool
    {
        return version_compare($candidate, $installed, '>');
    }

    public static function isSnapshotFresh(array $snapshot, int $nowTs, int $ttlSeconds): bool
    {
        $checkedAt = $snapshot['checkedAt'] ?? null;
        if (!is_int($checkedAt)) {
            return false;
        }

        return $checkedAt + $ttlSeconds > $nowTs;
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

        return [
            'forkVersion' => $version,
            'tag' => $tag,
            'publishedAt' => is_string($publishedAt) ? $publishedAt : null,
            'notesUrl' => is_string($notesUrl) ? $notesUrl : null,
            'assets' => $assets,
        ];
    }

    /**
     * @return array{zip: string, versionJson: string, checksums: string}|null
     */
    private static function mapAssets(array $assets, string $version): ?array
    {
        $zipName = "okaycms-fork-v{$version}.zip";
        $map = [];

        foreach ($assets as $asset) {
            if (!is_array($asset) || !isset($asset['name'], $asset['browser_download_url'])) {
                continue;
            }

            $key = match ($asset['name']) {
                $zipName => 'zip',
                'version.json' => 'versionJson',
                'checksums.txt' => 'checksums',
                default => null,
            };
            if ($key !== null) {
                $map[$key] = $asset['browser_download_url'];
            }
        }

        if (!isset($map['zip'], $map['versionJson'], $map['checksums'])) {
            return null;
        }

        return $map;
    }
}
