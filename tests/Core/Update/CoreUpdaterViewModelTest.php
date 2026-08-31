<?php

namespace Core\Update;

use Okay\Core\Update\CoreUpdaterViewModel;
use Okay\Core\Update\UpdateStatus;
use PHPUnit\Framework\TestCase;

class CoreUpdaterViewModelTest extends TestCase
{
    private const NOW = 1_000_000;

    public function testNoSnapshotAndNoRunIsNoData(): void
    {
        $result = CoreUpdaterViewModel::build(null, null, self::NOW);

        $this->assertSame('no_data', $result['mode']);
        $this->assertNull($result['installed']);
        $this->assertNull($result['latest']);
        $this->assertNull($result['run']);
        $this->assertFalse($result['canStartUpdate']);
        $this->assertTrue($result['canCheckNow']);
    }

    public function testEmptySnapshotIsAlsoNoData(): void
    {
        $result = CoreUpdaterViewModel::build([], null, self::NOW);

        $this->assertSame('no_data', $result['mode']);
    }

    public function testSnapshotWithoutUpdateIsUpToDate(): void
    {
        $snapshot = $this->snapshot(['updateAvailable' => false]);

        $result = CoreUpdaterViewModel::build($snapshot, null, self::NOW);

        $this->assertSame('up_to_date', $result['mode']);
        $this->assertSame('1.0.0', $result['installed']);
        $this->assertFalse($result['canStartUpdate']);
        $this->assertTrue($result['canCheckNow']);
    }

    public function testSnapshotWithUpdateIsUpdateAvailable(): void
    {
        $snapshot = $this->snapshot(['updateAvailable' => true, 'latest' => ['forkVersion' => '1.1.0']]);

        $result = CoreUpdaterViewModel::build($snapshot, null, self::NOW);

        $this->assertSame('update_available', $result['mode']);
        $this->assertSame(['forkVersion' => '1.1.0'], $result['latest']);
        $this->assertTrue($result['canStartUpdate']);
        $this->assertTrue($result['canCheckNow']);
    }

    public function testActiveNonTerminalNonStaleRunIsRunning(): void
    {
        $snapshot = $this->snapshot(['updateAvailable' => true]);
        $runState = $this->runState(UpdateStatus::STEP_APPLY_FILES, self::NOW - 60);

        $result = CoreUpdaterViewModel::build($snapshot, $runState, self::NOW);

        $this->assertSame('running', $result['mode']);
        $this->assertFalse($result['canStartUpdate']);
        $this->assertFalse($result['canCheckNow']);
        $this->assertSame(UpdateStatus::STEP_APPLY_FILES, $result['run']['step']);
    }

    public function testRunningTakesPriorityOverUpdateAvailable(): void
    {
        $snapshot = $this->snapshot(['updateAvailable' => true]);
        $runState = $this->runState(UpdateStatus::STEP_DOWNLOAD, self::NOW - 1);

        $result = CoreUpdaterViewModel::build($snapshot, $runState, self::NOW);

        $this->assertSame('running', $result['mode']);
    }

    public function testStaleNonTerminalRunIsStaleRun(): void
    {
        $runState = $this->runState(UpdateStatus::STEP_MIGRATIONS, self::NOW - 601);

        $result = CoreUpdaterViewModel::build(null, $runState, self::NOW);

        $this->assertSame('stale_run', $result['mode']);
        $this->assertTrue($result['canStartUpdate']);
        $this->assertTrue($result['canCheckNow']);
    }

    public function testStaleBoundaryAtExactlySixHundredSecondsIsStillRunning(): void
    {
        // Дзеркалить нерівність UpdateStatus::isStale(): stale лише коли
        // (now - updatedAt) > 600, тож рівно 600 — ще не stale.
        $runState = $this->runState(UpdateStatus::STEP_MIGRATIONS, self::NOW - 600);

        $result = CoreUpdaterViewModel::build(null, $runState, self::NOW);

        $this->assertSame('running', $result['mode']);
    }

    public function testStaleBoundaryAtSixHundredAndOneSecondsIsStale(): void
    {
        $runState = $this->runState(UpdateStatus::STEP_MIGRATIONS, self::NOW - 601);

        $result = CoreUpdaterViewModel::build(null, $runState, self::NOW);

        $this->assertSame('stale_run', $result['mode']);
    }

    public function testDoneRunWithoutNewReleaseCannotStartUpdate(): void
    {
        $snapshot = $this->snapshot(['updateAvailable' => false]);
        $runState = $this->runState(UpdateStatus::STEP_DONE, self::NOW - 5);

        $result = CoreUpdaterViewModel::build($snapshot, $runState, self::NOW);

        $this->assertSame('done', $result['mode']);
        $this->assertFalse($result['canStartUpdate']);
        $this->assertTrue($result['canCheckNow']);
    }

    public function testDoneRunWithNewReleaseReopensAsUpdateAvailable(): void
    {
        // C2: 'done' лишається лише коли новішого релізу нема — інакше кнопка
        // старту наступного оновлення була б назавжди недосяжна.
        $snapshot = $this->snapshot(['updateAvailable' => true]);
        $runState = $this->runState(UpdateStatus::STEP_DONE, self::NOW - 5);
        $runState['finalizeWarning'] = 'не вдалося прибрати тимчасові файли';

        $result = CoreUpdaterViewModel::build($snapshot, $runState, self::NOW);

        $this->assertSame('update_available', $result['mode']);
        $this->assertTrue($result['canStartUpdate']);
        $this->assertTrue($result['previousRunDone']);
        $this->assertSame('не вдалося прибрати тимчасові файли', $result['run']['finalizeWarning']);
    }

    public function testPreviousRunDoneIsFalseInAllOtherModes(): void
    {
        $result = CoreUpdaterViewModel::build(null, null, self::NOW);

        $this->assertFalse($result['previousRunDone']);
    }

    public function testFailedRunExposesErrorAndManualInterventionFlags(): void
    {
        $runState = $this->runState(UpdateStatus::STEP_FAILED, self::NOW - 5);
        $runState['error'] = 'HTTP 500';
        $runState['requiresManualIntervention'] = true;
        $runState['maintenanceDisabledAfterFailure'] = true;

        $result = CoreUpdaterViewModel::build(null, $runState, self::NOW);

        $this->assertSame('failed', $result['mode']);
        $this->assertSame('HTTP 500', $result['run']['error']);
        $this->assertTrue($result['run']['requiresManualIntervention']);
        $this->assertTrue($result['run']['maintenanceDisabledAfterFailure']);
        $this->assertTrue($result['canCheckNow']);
        $this->assertFalse($result['canStartUpdate']);
    }

    public function testRolledBackRunExposesMigrationsAndBackupPaths(): void
    {
        $runState = $this->runState(UpdateStatus::STEP_ROLLED_BACK, self::NOW - 5);
        $runState['rolledBackMigrations'] = ['1.1.0_add_column.up.sql'];
        $runState['migrationsDumpPath'] = '/files/backups/dump.sql';
        $runState['backupZipPath'] = '/files/backups/pre-update.zip';

        $result = CoreUpdaterViewModel::build(null, $runState, self::NOW);

        $this->assertSame('rolled_back', $result['mode']);
        $this->assertSame(['1.1.0_add_column.up.sql'], $result['run']['rolledBackMigrations']);
        $this->assertSame('/files/backups/dump.sql', $result['run']['migrationsDumpPath']);
        $this->assertSame('/files/backups/pre-update.zip', $result['run']['backupZipPath']);
    }

    public function testMissingSnapshotKeysFromOlderModuleVersionDoNotBreak(): void
    {
        $result = CoreUpdaterViewModel::build(['installed' => '1.0.0'], null, self::NOW);

        $this->assertSame('up_to_date', $result['mode']);
        $this->assertSame('1.0.0', $result['installed']);
        $this->assertNull($result['latest']);
        $this->assertNull($result['checkedAt']);
        $this->assertNull($result['lastError']);
    }

    public function testMissingRunStateKeysDoNotBreak(): void
    {
        $runState = ['step' => UpdateStatus::STEP_APPLY_FILES, 'updatedAt' => self::NOW - 1];

        $result = CoreUpdaterViewModel::build(null, $runState, self::NOW);

        $this->assertSame('running', $result['mode']);
        $this->assertNull($result['run']['error']);
        $this->assertNull($result['run']['rolledBackMigrations']);
        $this->assertNull($result['run']['backupZipPath']);
        $this->assertFalse($result['run']['requiresManualIntervention']);
    }

    public function testRunStepIndexAndStepsTotalComeFromUpdateStatusSteps(): void
    {
        $runState = $this->runState(UpdateStatus::STEP_MIGRATIONS, self::NOW - 1);

        $result = CoreUpdaterViewModel::build(null, $runState, self::NOW);

        $this->assertSame(
            array_search(UpdateStatus::STEP_MIGRATIONS, UpdateStatus::STEPS, true),
            $result['run']['stepIndex']
        );
        $this->assertSame(count(UpdateStatus::STEPS), $result['run']['stepsTotal']);
    }

    public function testInstalledUpstreamBaseIsPassedThroughWhenGiven(): void
    {
        $snapshot = $this->snapshot(['updateAvailable' => false]);

        $result = CoreUpdaterViewModel::build($snapshot, null, self::NOW, '4.5.2');

        $this->assertSame('4.5.2', $result['installedUpstreamBase']);
    }

    public function testInstalledUpstreamBaseDefaultsToNull(): void
    {
        $result = CoreUpdaterViewModel::build(null, null, self::NOW);

        $this->assertNull($result['installedUpstreamBase']);
    }

    public function testSnapshotLastErrorIsExposedAlongsideUpToDateMode(): void
    {
        $snapshot = $this->snapshot(['updateAvailable' => false, 'lastError' => 'HTTP 500', 'checkedAt' => self::NOW - 10]);

        $result = CoreUpdaterViewModel::build($snapshot, null, self::NOW);

        $this->assertSame('HTTP 500', $result['lastError']);
        $this->assertSame(self::NOW - 10, $result['checkedAt']);
    }

    /** @param array<string, mixed> $overrides */
    private function snapshot(array $overrides): array
    {
        return array_merge([
            'checkedAt' => self::NOW - 100,
            'etag' => 'abc',
            'installed' => '1.0.0',
            'latest' => null,
            'updateAvailable' => false,
        ], $overrides);
    }

    /**
     * Стан прогону живе в Settings назавжди, тож без вікна свіжості
     * «Оновлення завершено» висіло б на сторінці й через місяць.
     */
    public function testStaleSuccessfulRunStopsBeingShownAsResult(): void
    {
        $runState = $this->runState(UpdateStatus::STEP_DONE, self::NOW - CoreUpdaterViewModel::RESULT_FRESH_SECONDS - 1);

        $vm = CoreUpdaterViewModel::build($this->snapshot(['updateAvailable' => false]), $runState, self::NOW);

        $this->assertSame('up_to_date', $vm['mode']);
        $this->assertFalse($vm['previousRunDone']);
    }

    public function testFreshSuccessfulRunIsStillShownAsResult(): void
    {
        $runState = $this->runState(UpdateStatus::STEP_DONE, self::NOW - 60);

        $vm = CoreUpdaterViewModel::build($this->snapshot(['updateAvailable' => false]), $runState, self::NOW);

        $this->assertSame(UpdateStatus::STEP_DONE, $vm['mode']);
    }

    /**
     * Невдалий прогін під вікно свіжості не підпадає: він вимагає дії —
     * можливо, лишились технічні роботи або напівзастосоване дерево.
     */
    public function testStaleFailedRunRemainsVisible(): void
    {
        $runState = $this->runState(UpdateStatus::STEP_FAILED, self::NOW - CoreUpdaterViewModel::RESULT_FRESH_SECONDS * 30);

        $vm = CoreUpdaterViewModel::build($this->snapshot(['updateAvailable' => false]), $runState, self::NOW);

        $this->assertSame(UpdateStatus::STEP_FAILED, $vm['mode']);
    }

    /**
     * Штатний шлях зникнення результату — не час, а перегляд: інакше зелене
     * «Оновлення завершено» вітало б адміна на кожному відкритті сторінки
     * цілу добу й читалось як «щойно оновились».
     */
    public function testSeenSuccessfulRunStopsBeingShownEvenWhileFresh(): void
    {
        $runState = $this->runState(UpdateStatus::STEP_DONE, self::NOW - 60);
        $runState['resultSeenAt'] = self::NOW - 30;

        $vm = CoreUpdaterViewModel::build($this->snapshot(['updateAvailable' => false]), $runState, self::NOW);

        $this->assertSame('up_to_date', $vm['mode']);
        $this->assertFalse($vm['previousRunDone']);
    }

    /** Невдалий прогін позначка «показано» не ховає — він вимагає дії. */
    public function testSeenFailedRunRemainsVisible(): void
    {
        $runState = $this->runState(UpdateStatus::STEP_FAILED, self::NOW - 60);
        $runState['resultSeenAt'] = self::NOW - 30;

        $vm = CoreUpdaterViewModel::build($this->snapshot(['updateAvailable' => false]), $runState, self::NOW);

        $this->assertSame(UpdateStatus::STEP_FAILED, $vm['mode']);
    }

    public function testStaleSuccessfulRunDoesNotAnnouncePreviousRunWhenUpdateAvailable(): void
    {
        $runState = $this->runState(UpdateStatus::STEP_DONE, self::NOW - CoreUpdaterViewModel::RESULT_FRESH_SECONDS - 1);

        $vm = CoreUpdaterViewModel::build($this->snapshot(['updateAvailable' => true, 'latest' => ['forkVersion' => '1.1.0']]), $runState, self::NOW);

        $this->assertSame('update_available', $vm['mode']);
        $this->assertTrue($vm['canStartUpdate']);
        $this->assertFalse($vm['previousRunDone']);
    }

    /** @return array<string, mixed> */
    private function runState(string $step, int $updatedAt): array
    {
        return [
            'step' => $step,
            'fromVersion' => '1.0.0',
            'toVersion' => '1.1.0',
            'updatedAt' => $updatedAt,
        ];
    }
}
