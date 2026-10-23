<?php

namespace Core\Update;

use Okay\Core\Update\UpdateCheckHelper;
use PHPUnit\Framework\TestCase;

class UpdateCheckHelperTest extends TestCase
{
    public function testBuildErrorResultFillsFullContractWhenNoStoredSnapshotExists(): void
    {
        $before = time();
        $result = UpdateCheckHelper::buildErrorResult(null, '1.0.0', 'HTTP 500');
        $after = time();

        $lastErrorAt = $result['lastErrorAt'];
        unset($result['lastErrorAt']);

        $this->assertSame([
            'checkedAt' => null,
            'etag' => null,
            'installed' => '1.0.0',
            'latest' => null,
            'updateAvailable' => false,
            'lastError' => 'HTTP 500',
        ], $result);
        $this->assertGreaterThanOrEqual($before, $lastErrorAt);
        $this->assertLessThanOrEqual($after, $lastErrorAt);
    }

    public function testBuildErrorResultKeepsStoredSnapshotValuesAndOnlyAddsLastError(): void
    {
        $stored = [
            'checkedAt' => 1000,
            'etag' => 'W/"abc"',
            'installed' => '1.0.0',
            'latest' => ['forkVersion' => '1.1.0'],
            'updateAvailable' => true,
        ];

        $result = UpdateCheckHelper::buildErrorResult($stored, '1.0.0', 'cURL error #28: timeout');
        $lastErrorAt = $result['lastErrorAt'];
        unset($result['lastErrorAt']);

        $this->assertSame($stored + ['lastError' => 'cURL error #28: timeout'], $result);
        $this->assertIsInt($lastErrorAt);
    }

    public function testBuildErrorResultFillsGapsInALegacyStoredSnapshotMissingKeys(): void
    {
        // Захист від майбутнього дрейфу формату снапшоту: часткові дані з
        // Settings теж добиваються до повного контракту check().
        $result = UpdateCheckHelper::buildErrorResult(['etag' => 'abc'], '2.0.0', 'HTTP 503');
        $lastErrorAt = $result['lastErrorAt'];
        unset($result['lastErrorAt']);

        $this->assertSame([
            'checkedAt' => null,
            'etag' => 'abc',
            'installed' => '2.0.0',
            'latest' => null,
            'updateAvailable' => false,
            'lastError' => 'HTTP 503',
        ], $result);
        $this->assertIsInt($lastErrorAt);
    }

    /** Після 301 у заголовках дві відповіді; ETag має бути з кінцевої. */
    public function testExtractEtagTakesTheFinalResponseAfterRedirect(): void
    {
        $headers = "HTTP/2 301\r\nlocation: https://api.github.com/repositories/1/releases\r\netag: \"old\"\r\n\r\n"
            . "HTTP/2 200\r\ncontent-type: application/json\r\nETag: W/\"final\"\r\n\r\n";

        $this->assertSame('W/"final"', UpdateCheckHelper::extractEtag($headers));
        $this->assertSame('W/"one"', UpdateCheckHelper::extractEtag("HTTP/2 200\r\nETag: W/\"one\"\r\n\r\n"));
        $this->assertNull(UpdateCheckHelper::extractEtag("HTTP/2 200\r\ncontent-type: x\r\n\r\n"));
    }
}
