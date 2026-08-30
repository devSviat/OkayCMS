<?php

namespace Modules\OkayCMS\CoreUpdater;

use Okay\Core\Config;
use Okay\Core\Design;
use Okay\Core\Release\CoreMigrator;
use Okay\Core\Settings;
use Okay\Modules\OkayCMS\CoreUpdater\Helpers\UpdateApplier;
use Okay\Modules\OkayCMS\CoreUpdater\Helpers\UpdateBackup;
use Okay\Modules\OkayCMS\CoreUpdater\Helpers\UpdateCheckHelper;
use Okay\Modules\OkayCMS\CoreUpdater\Helpers\UpdateDownloader;
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

    // --- preflight: тег релізу проти version.json пакета ---

    private function buildRunner(): UpdateRunner
    {
        return new UpdateRunner(
            $this->createStub(UpdateCheckHelper::class),
            $this->createStub(UpdateStatus::class),
            $this->createStub(UpdateDownloader::class),
            $this->createStub(UpdateBackup::class),
            $this->createStub(UpdateApplier::class),
            $this->createStub(CoreMigrator::class),
            $this->createStub(Settings::class),
            $this->createStub(Config::class),
            $this->createStub(Design::class)
        );
    }

    /** @param array<string, mixed> $ctx @return array<string, mixed> */
    private function invokeStepPreflight(array $ctx): array
    {
        return (new \ReflectionMethod(UpdateRunner::class, 'stepPreflight'))->invoke($this->buildRunner(), $ctx);
    }

    /**
     * Перевірка стоїть ПЕРШОЮ в stepPreflight саме тому, що вона єдина не
     * потребує ні диска, ні composer — тест доходить до неї, не готуючи
     * дерево. Розбіжність означає, що до тегу причепили чужий архів.
     */
    public function testPreflightRefusesAPackageWhoseVersionJsonDisagreesWithTheReleaseTag(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/1\.3\.0.+1\.2\.0/');

        $this->invokeStepPreflight([
            'rootDir' => sys_get_temp_dir(),
            'toVersion' => '1.2.0',
            'fromVersion' => '1.1.0',
            'versionMeta' => ['forkVersion' => '1.3.0'],
            'extractDir' => sys_get_temp_dir(),
        ]);
    }

    public function testPreflightRefusesAPackageWithoutAVersionInVersionJson(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->invokeStepPreflight([
            'rootDir' => sys_get_temp_dir(),
            'toVersion' => '1.2.0',
            'fromVersion' => '1.1.0',
            'versionMeta' => [],
            'extractDir' => sys_get_temp_dir(),
        ]);
    }

    // --- health-check: рішення по одній відповіді ---

    public function testHealthResponseIsHealthyOnlyForTheExpectedVersion(): void
    {
        $this->assertTrue(UpdateRunner::isHealthyResponse(0, 200, '{"forkVersion":"1.2.0"}', '1.2.0', false));
        $this->assertFalse(UpdateRunner::isHealthyResponse(0, 200, '{"forkVersion":"1.1.0"}', '1.2.0', false));
    }

    public function testHealthResponseIsUnhealthyOnTransportOrHttpFailure(): void
    {
        $this->assertFalse(UpdateRunner::isHealthyResponse(7, 0, null, '1.2.0', false));
        $this->assertFalse(UpdateRunner::isHealthyResponse(0, 500, '{"forkVersion":"1.2.0"}', '1.2.0', false));
        $this->assertFalse(UpdateRunner::isHealthyResponse(0, 200, null, '1.2.0', false));
    }

    /**
     * Forward-гілка: нова версія ЗОБОВ'ЯЗАНА відповісти своїм JSON, тож
     * 200 зі сторінкою вітрини — це не «живий», а «ендпоінта немає».
     */
    public function testHealthResponseRejectsNonJsonBodyOnTheForwardCheck(): void
    {
        $this->assertFalse(UpdateRunner::isHealthyResponse(0, 200, '<html>вітрина</html>', '1.2.0', false));
    }

    /**
     * Rollback-гілка: перше в житті інсталяції оновлення відкочується на
     * версію, чий index.php не знає ні прапорця, ні ?core_updater_health —
     * 200 звідти доводить рівно те, що треба довести. Без цього послаблення
     * успішний відкат рапортував би «потрібне ручне втручання».
     */
    public function testHealthResponseAcceptsA200WithoutVersionOnTheRollbackCheck(): void
    {
        $this->assertTrue(UpdateRunner::isHealthyResponse(0, 200, '<html>вітрина</html>', '1.1.0', true));
        $this->assertTrue(UpdateRunner::isHealthyResponse(0, 200, '{"other":"json"}', '1.1.0', true));
    }

    /** Послаблення стосується лише ВІДСУТНЬОЇ версії, не чужої. */
    public function testHealthResponseStillRejectsAWrongVersionOnTheRollbackCheck(): void
    {
        $this->assertFalse(UpdateRunner::isHealthyResponse(0, 200, '{"forkVersion":"1.2.0"}', '1.1.0', true));
    }
}
