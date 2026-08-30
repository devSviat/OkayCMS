<?php

namespace Modules\OkayCMS\CoreUpdater;

use Okay\Modules\OkayCMS\CoreUpdater\Helpers\ReleaseFeed;
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
            'assets' => [
                'zip' => 'https://example.com/releases/1.1.0/okaycms-fork-v1.1.0.zip',
                'versionJson' => 'https://example.com/releases/1.1.0/version.json',
                'checksums' => 'https://example.com/releases/1.1.0/checksums.txt',
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
}
