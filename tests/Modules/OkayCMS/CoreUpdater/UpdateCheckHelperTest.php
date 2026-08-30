<?php

namespace Modules\OkayCMS\CoreUpdater;

use Okay\Modules\OkayCMS\CoreUpdater\Helpers\UpdateCheckHelper;
use PHPUnit\Framework\TestCase;

class UpdateCheckHelperTest extends TestCase
{
    public function testBuildErrorResultFillsFullContractWhenNoStoredSnapshotExists(): void
    {
        $result = UpdateCheckHelper::buildErrorResult(null, '1.0.0', 'HTTP 500');

        $this->assertSame([
            'checkedAt' => null,
            'etag' => null,
            'installed' => '1.0.0',
            'latest' => null,
            'updateAvailable' => false,
            'lastError' => 'HTTP 500',
        ], $result);
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

        $this->assertSame($stored + ['lastError' => 'cURL error #28: timeout'], $result);
    }

    public function testBuildErrorResultFillsGapsInALegacyStoredSnapshotMissingKeys(): void
    {
        // Захист від майбутнього дрейфу формату снапшоту: часткові дані з
        // Settings теж добиваються до повного контракту check().
        $result = UpdateCheckHelper::buildErrorResult(['etag' => 'abc'], '2.0.0', 'HTTP 503');

        $this->assertSame([
            'checkedAt' => null,
            'etag' => 'abc',
            'installed' => '2.0.0',
            'latest' => null,
            'updateAvailable' => false,
            'lastError' => 'HTTP 503',
        ], $result);
    }
}
