<?php

namespace Core\Update;

use Okay\Core\Update\UpdateStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UpdateStatusTest extends TestCase
{
    public function testFreshReturnsFirstStepWithVersions(): void
    {
        $before = time();
        $state = UpdateStatus::fresh('1.0.0', '1.1.0');
        $after = time();

        $this->assertSame(UpdateStatus::STEPS[0], $state['step']);
        $this->assertSame('1.0.0', $state['fromVersion']);
        $this->assertSame('1.1.0', $state['toVersion']);
        $this->assertGreaterThanOrEqual($before, $state['updatedAt']);
        $this->assertLessThanOrEqual($after, $state['updatedAt']);
    }

    public function testAdvanceWalksThroughEveryStepInOrderUpToDone(): void
    {
        $state = UpdateStatus::fresh('1.0.0', '1.1.0');

        $remainingSteps = array_slice(UpdateStatus::STEPS, 1);
        foreach ($remainingSteps as $step) {
            $state = UpdateStatus::advance($state, $step);
            $this->assertSame($step, $state['step']);
        }

        $state = UpdateStatus::advance($state, UpdateStatus::STEP_DONE);
        $this->assertSame(UpdateStatus::STEP_DONE, $state['step']);
    }

    public function testAdvanceMergesExtraDataAndBumpsUpdatedAt(): void
    {
        $state = UpdateStatus::fresh('1.0.0', '1.1.0');
        $state['updatedAt'] = time() - 100;

        $before = time();
        $state = UpdateStatus::advance($state, UpdateStatus::STEP_VERIFY, ['progress' => 42]);
        $after = time();

        $this->assertSame(UpdateStatus::STEP_VERIFY, $state['step']);
        $this->assertSame(42, $state['progress']);
        $this->assertGreaterThanOrEqual($before, $state['updatedAt']);
        $this->assertLessThanOrEqual($after, $state['updatedAt']);
    }

    public function testAdvanceForbidsGoingBackwards(): void
    {
        $state = UpdateStatus::fresh('1.0.0', '1.1.0');
        $state = UpdateStatus::advance($state, UpdateStatus::STEP_VERIFY);
        $state = UpdateStatus::advance($state, UpdateStatus::STEP_PREFLIGHT);

        $this->expectException(\LogicException::class);
        UpdateStatus::advance($state, UpdateStatus::STEP_VERIFY);
    }

    public function testAdvanceForbidsRepeatingTheCurrentStep(): void
    {
        $state = UpdateStatus::fresh('1.0.0', '1.1.0');

        $this->expectException(\LogicException::class);
        UpdateStatus::advance($state, UpdateStatus::STEP_DOWNLOAD);
    }

    public function testAdvanceForbidsSkippingToAnUnknownStep(): void
    {
        $state = UpdateStatus::fresh('1.0.0', '1.1.0');

        $this->expectException(\LogicException::class);
        UpdateStatus::advance($state, 'teleport');
    }

    public function testAdvanceForbidsMovingPastATerminalState(): void
    {
        $state = UpdateStatus::fail(UpdateStatus::fresh('1.0.0', '1.1.0'), 'boom');

        $this->expectException(\LogicException::class);
        UpdateStatus::advance($state, UpdateStatus::STEP_VERIFY);
    }

    #[DataProvider('stepsProvider')]
    public function testFailIsAllowedFromAnyStep(string $step): void
    {
        $state = UpdateStatus::fresh('1.0.0', '1.1.0');
        $state['step'] = $step;

        $before = time();
        $result = UpdateStatus::fail($state, 'network unreachable');
        $after = time();

        $this->assertSame(UpdateStatus::STEP_FAILED, $result['step']);
        $this->assertSame('network unreachable', $result['error']);
        $this->assertGreaterThanOrEqual($before, $result['updatedAt']);
        $this->assertLessThanOrEqual($after, $result['updatedAt']);
    }

    public static function stepsProvider(): array
    {
        return array_map(static fn(string $step) => [$step], UpdateStatus::STEPS);
    }

    public function testRolledBackRecordsAppliedMigrations(): void
    {
        $state = UpdateStatus::fresh('1.0.0', '1.1.0');
        $state['step'] = UpdateStatus::STEP_MIGRATIONS;

        $result = UpdateStatus::rolledBack($state, ['1.1.0_add_column.up.sql']);

        $this->assertSame(UpdateStatus::STEP_ROLLED_BACK, $result['step']);
        $this->assertSame(['1.1.0_add_column.up.sql'], $result['rolledBackMigrations']);
    }

    public function testIsStaleIsFalseForARecentlyUpdatedNonTerminalStep(): void
    {
        $now = 1_000_000;
        $state = ['step' => UpdateStatus::STEP_APPLY_FILES, 'updatedAt' => $now - 60];

        $this->assertFalse(UpdateStatus::isStale($state, $now));
    }

    public function testIsStaleIsTrueOncePastTenMinutesForANonTerminalStep(): void
    {
        $now = 1_000_000;
        $state = ['step' => UpdateStatus::STEP_APPLY_FILES, 'updatedAt' => $now - 601];

        $this->assertTrue(UpdateStatus::isStale($state, $now));
    }

    public function testIsStaleIsFalseRightAtTheTenMinuteBoundary(): void
    {
        $now = 1_000_000;
        $state = ['step' => UpdateStatus::STEP_APPLY_FILES, 'updatedAt' => $now - 600];

        $this->assertFalse(UpdateStatus::isStale($state, $now));
    }

    #[DataProvider('terminalStepsProvider')]
    public function testIsStaleIsAlwaysFalseForTerminalSteps(string $step): void
    {
        $now = 1_000_000;
        $state = ['step' => $step, 'updatedAt' => $now - 100_000];

        $this->assertFalse(UpdateStatus::isStale($state, $now));
    }

    public static function terminalStepsProvider(): array
    {
        return array_map(static fn(string $step) => [$step], UpdateStatus::TERMINAL_STEPS);
    }
}
