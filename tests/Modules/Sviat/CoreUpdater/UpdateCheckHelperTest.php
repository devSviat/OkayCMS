<?php

namespace Modules\Sviat\CoreUpdater;

use Okay\Modules\Sviat\CoreUpdater\Helpers\UpdateCheckHelper;
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
}
