<?php

namespace Modules\Sviat\CoreUpdater;

use Okay\Modules\Sviat\CoreUpdater\Helpers\ReleaseFeed;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ReleaseFeedTest extends TestCase
{
    private static function fixture(string $name): string
    {
        return file_get_contents(__DIR__ . '/fixtures/' . $name);
    }

    public function testParseLatestPicksHighestVersionRegardlessOfListOrder(): void
    {
        $result = ReleaseFeed::parseLatest(self::fixture('releases.json'));

        $this->assertSame([
            'forkVersion' => '1.1.0',
            'tag' => 'okaycms-fork/v1.1.0',
            'publishedAt' => '2026-02-15T00:00:00Z',
            'notesUrl' => 'https://github.com/devSviat/OkayCMS/releases/tag/okaycms-fork%2Fv1.1.0',
            'notesBody' => 'Release notes for 1.1.0',
            'assets' => [
                'zip' => 'https://github.com/devSviat/OkayCMS/releases/download/okaycms-fork/v1.1.0/okaycms-fork-v1.1.0.zip',
                'versionJson' => 'https://github.com/devSviat/OkayCMS/releases/download/okaycms-fork/v1.1.0/version.json',
                'checksums' => 'https://github.com/devSviat/OkayCMS/releases/download/okaycms-fork/v1.1.0/checksums.txt',
            ],
        ], $result);
    }

    public function testParseLatestSkipsUpstreamTagsRcDraftPrereleaseAndIncompleteAssets(): void
    {
        // 4.6.0 (не форк-тег), 1.2.0-rc1 (не рівно \d+.\d+.\d+), 1.4.0
        // (draft), 1.5.0 (prerelease) і 1.3.0 (без checksums.txt) старші за
        // версією, ніж 1.1.0, тож тест ловить пропуск будь-якого з фільтрів.
        $result = ReleaseFeed::parseLatest(self::fixture('releases-with-noise.json'));

        $this->assertNotNull($result);
        $this->assertSame('1.1.0', $result['forkVersion']);
    }

    public function testParseLatestReturnsNullOnBrokenJson(): void
    {
        $this->assertNull(ReleaseFeed::parseLatest('{not valid json'));
    }

    public function testParseLatestReturnsNullOnEmptyArray(): void
    {
        $this->assertNull(ReleaseFeed::parseLatest('[]'));
    }

    public function testParseLatestSkipsReleaseWhoseZipNameEncodesADifferentVersionThanItsTag(): void
    {
        // v2.0.0 сам себе дискваліфікує: зип названо під v1.9.9, тому
        // очікуваного okaycms-fork-v2.0.0.zip серед asset'ів немає і
        // mapAssets() не знаходить 'zip' — лишається лише повний v1.0.0.
        $result = ReleaseFeed::parseLatest(self::fixture('releases-mismatched-zip-version.json'));

        $this->assertNotNull($result);
        $this->assertSame('1.0.0', $result['forkVersion']);
    }

    #[DataProvider('newerThanInstalledProvider')]
    public function testIsNewerThanInstalled(string $candidate, string $installed, bool $expected): void
    {
        $this->assertSame($expected, ReleaseFeed::isNewerThanInstalled($candidate, $installed));
    }

    public static function newerThanInstalledProvider(): array
    {
        return [
            'candidate newer' => ['1.1.0', '1.0.0', true],
            'candidate older' => ['1.0.0', '1.1.0', false],
            'candidate equal' => ['1.0.0', '1.0.0', false],
        ];
    }

    #[DataProvider('snapshotFreshnessProvider')]
    public function testIsSnapshotFresh(array $snapshot, int $nowTs, int $ttlSeconds, bool $expected): void
    {
        $this->assertSame($expected, ReleaseFeed::isSnapshotFresh($snapshot, $nowTs, $ttlSeconds));
    }

    public static function snapshotFreshnessProvider(): array
    {
        return [
            'fresh' => [['checkedAt' => 1000], 1000 + 3600 - 1, 3600, true],
            'stale' => [['checkedAt' => 1000], 1000 + 3600 + 1, 3600, false],
            'missing checkedAt' => [[], 2000, 3600, false],
            'broken checkedAt' => [['checkedAt' => 'not-a-timestamp'], 2000, 3600, false],
        ];
    }

    public function testParseLatestSkipsAssetWithNonStringDownloadUrl(): void
    {
        // v2.0.0 має нечислове поле browser_download_url у zip-асеті,
        // тому набір неповний і весь реліз відсіюється, лишається v1.0.0.
        $result = ReleaseFeed::parseLatest(self::fixture('releases-non-string-asset-url.json'));

        $this->assertNotNull($result);
        $this->assertSame('1.0.0', $result['forkVersion']);
    }

    public function testParseLatestSkipsAssetsFromUntrustedOrigin(): void
    {
        // v2.0.0 має zip по http:// і version.json з чужого хоста —
        // жодне з них не проходить перевірку префіксу, реліз відсіюється.
        $result = ReleaseFeed::parseLatest(self::fixture('releases-untrusted-asset-url.json'));

        $this->assertNotNull($result);
        $this->assertSame('1.0.0', $result['forkVersion']);
    }

    #[DataProvider('refreshInstalledProvider')]
    public function testRefreshInstalled(array $snapshot, string $installed, bool $expectedUpdateAvailable): void
    {
        $result = ReleaseFeed::refreshInstalled($snapshot, $installed);

        $this->assertSame($installed, $result['installed']);
        $this->assertSame($expectedUpdateAvailable, $result['updateAvailable']);
    }

    public static function refreshInstalledProvider(): array
    {
        return [
            'update available then applied flips to false' => [
                ['installed' => '1.0.0', 'updateAvailable' => true, 'latest' => ['forkVersion' => '1.1.0']],
                '1.1.0',
                false,
            ],
            'missing latest stays false' => [
                ['installed' => '1.0.0', 'updateAvailable' => false, 'latest' => null],
                '1.0.0',
                false,
            ],
            'genuinely newer release stays true' => [
                ['installed' => '1.0.0', 'updateAvailable' => true, 'latest' => ['forkVersion' => '1.1.0']],
                '1.0.0',
                true,
            ],
        ];
    }

    public function testParseVersionMetaReadsAllFieldsFromValidJson(): void
    {
        $json = json_encode([
            'upstreamBase' => '4.5.2',
            'minPhp' => '8.4',
            'requiresMigrations' => true,
            'releasedAt' => '2026-02-15T00:00:00Z',
        ]);

        $this->assertSame([
            'upstreamBase' => '4.5.2',
            'minPhp' => '8.4',
            'requiresMigrations' => true,
            'releasedAt' => '2026-02-15T00:00:00Z',
        ], ReleaseFeed::parseVersionMeta($json));
    }

    public function testParseVersionMetaFillsMissingOrMistypedFieldsWithNull(): void
    {
        $json = json_encode([
            'upstreamBase' => '4.5.2',
            'minPhp' => 12345,
        ]);

        $this->assertSame([
            'upstreamBase' => '4.5.2',
            'minPhp' => null,
            'requiresMigrations' => null,
            'releasedAt' => null,
        ], ReleaseFeed::parseVersionMeta($json));
    }

    public function testParseVersionMetaReturnsNullOnBrokenJson(): void
    {
        $this->assertNull(ReleaseFeed::parseVersionMeta('{not valid json'));
    }
}
