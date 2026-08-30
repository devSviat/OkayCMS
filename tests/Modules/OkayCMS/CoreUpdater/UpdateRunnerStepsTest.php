<?php

namespace Modules\OkayCMS\CoreUpdater;

use Okay\Modules\OkayCMS\CoreUpdater\Helpers\UpdateRunner;
use Okay\Modules\OkayCMS\CoreUpdater\Helpers\UpdateStatus;
use PHPUnit\Framework\TestCase;

class UpdateRunnerStepsTest extends TestCase
{
    // --- stepHandlers() completeness ---

    public function testStepHandlersCoverEveryStepInSpecOrder(): void
    {
        $this->assertSame(UpdateStatus::STEPS, array_keys(UpdateRunner::stepHandlers()));
    }

    public function testStepHandlersReferenceExistingMethods(): void
    {
        $reflection = new \ReflectionClass(UpdateRunner::class);

        foreach (UpdateRunner::stepHandlers() as $step => $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                "Крок '{$step}' посилається на неіснуючий метод '{$method}'."
            );
        }
    }

    // --- needsRollback() ---

    public function testNeedsRollbackIsFalseBeforeApplyFilesStarted(): void
    {
        foreach ([
            UpdateStatus::STEP_DOWNLOAD,
            UpdateStatus::STEP_VERIFY,
            UpdateStatus::STEP_PREFLIGHT,
            UpdateStatus::STEP_BACKUP,
            UpdateStatus::STEP_MAINTENANCE_ON,
        ] as $step) {
            $this->assertFalse(UpdateRunner::needsRollback(['step' => $step]), "step={$step}");
        }
    }

    public function testNeedsRollbackIsTrueFromApplyFilesThroughHealthCheck(): void
    {
        foreach ([
            UpdateStatus::STEP_APPLY_FILES,
            UpdateStatus::STEP_MIGRATIONS,
            UpdateStatus::STEP_CACHE_CLEAR,
            UpdateStatus::STEP_HEALTH_CHECK,
        ] as $step) {
            $this->assertTrue(UpdateRunner::needsRollback(['step' => $step]), "step={$step}");
        }
    }

    public function testNeedsRollbackIsFalseAtFinalizeEvenThoughFilesWereApplied(): void
    {
        // finalize настає лише ПІСЛЯ успішного health_check — сайт уже
        // підтверджено живий на новій версії, провал прибирання тимчасових
        // файлів там не привід відкочувати робочий сайт назад.
        $this->assertFalse(UpdateRunner::needsRollback(['step' => UpdateStatus::STEP_FINALIZE]));
    }

    public function testNeedsRollbackIsFalseForTerminalSteps(): void
    {
        $this->assertFalse(UpdateRunner::needsRollback(['step' => UpdateStatus::STEP_DONE]));
        $this->assertFalse(UpdateRunner::needsRollback(['step' => UpdateStatus::STEP_FAILED]));
        $this->assertFalse(UpdateRunner::needsRollback(['step' => UpdateStatus::STEP_ROLLED_BACK]));
    }

    public function testNeedsRollbackIsFalseForUnknownOrMissingStep(): void
    {
        $this->assertFalse(UpdateRunner::needsRollback(['step' => 'no-such-step']));
        $this->assertFalse(UpdateRunner::needsRollback([]));
    }
}
