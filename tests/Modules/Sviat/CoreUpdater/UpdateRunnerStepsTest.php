<?php

namespace Modules\Sviat\CoreUpdater;

use Okay\Core\Config;
use Okay\Core\Design;
use Okay\Core\Release\CoreMigrator;
use Okay\Core\Settings;
use Okay\Core\Update\UpdateApplier;
use Okay\Core\Update\UpdateBackup;
use Okay\Core\Update\UpdateCheckHelper;
use Okay\Core\Update\UpdateDownloader;
use Okay\Core\Update\UpdateRunner;
use Okay\Core\Update\UpdateStatus;
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

    // --- preflight: проба self-request ---

    /**
     * Проба стоїть ОСТАННЬОЮ в stepPreflight: до неї мають дійти лише
     * прогони, що пройшли решту перевірок. Тут — що вона взагалі
     * викликається і її провал зупиняє preflight (пул з одним воркером).
     */
    public function testPreflightAbortsWhenSelfRequestProbeFails(): void
    {
        $baseDir = sys_get_temp_dir() . '/runner-probe-test-' . uniqid('', true);
        $rootDir = $baseDir . '/root';
        $extractDir = $baseDir . '/extract';
        mkdir($extractDir . '/payload', 0777, true);
        mkdir($rootDir . '/Okay/Core', 0777, true);
        mkdir($rootDir . '/config', 0777, true);

        $runner = $this->getMockBuilder(UpdateRunner::class)
            ->setConstructorArgs([
                $this->createStub(UpdateCheckHelper::class),
                $this->createStub(UpdateStatus::class),
                $this->createStub(UpdateDownloader::class),
                $this->createStub(UpdateBackup::class),
                $this->createStub(UpdateApplier::class),
                $this->createStub(CoreMigrator::class),
                $this->createStub(Settings::class),
                $this->createStub(Config::class),
                $this->createStub(Design::class),
            ])
            ->onlyMethods(['assertSelfRequestPossible'])
            ->getMock();
        $runner->expects($this->once())
            ->method('assertSelfRequestPossible')
            ->willThrowException(new \RuntimeException('Сайт не відповів сам собі'));

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessageMatches('/не відповів сам собі/');

            (new \ReflectionMethod(UpdateRunner::class, 'stepPreflight'))->invoke($runner, [
                'rootDir' => $rootDir,
                'toVersion' => '1.2.0',
                'fromVersion' => '1.1.0',
                'versionMeta' => ['forkVersion' => '1.2.0'],
                'extractDir' => $extractDir,
            ]);
        } finally {
            rmdir($extractDir . '/payload');
            rmdir($extractDir);
            rmdir($rootDir . '/Okay/Core');
            rmdir($rootDir . '/Okay');
            rmdir($rootDir . '/config');
            rmdir($rootDir);
            rmdir($baseDir);
        }
    }

    public function testSelfRequestProofAcceptsAnyCompletedHttpResponse(): void
    {
        $this->assertTrue(UpdateRunner::isSelfRequestProof(0, 200));
        $this->assertTrue(UpdateRunner::isSelfRequestProof(0, 500));
        $this->assertTrue(UpdateRunner::isSelfRequestProof(0, 503));
    }

    public function testSelfRequestProofRejectsTimeoutAndTransportFailures(): void
    {
        $this->assertFalse(UpdateRunner::isSelfRequestProof(28, 0));
        $this->assertFalse(UpdateRunner::isSelfRequestProof(7, 0));
        $this->assertFalse(UpdateRunner::isSelfRequestProof(0, 0));
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

    // --- resumesDeadRun(): єдине місце, що послаблює downgrade guard ---

    /** @param array<string, mixed>|null $savedState */
    private function invokeResumesDeadRun(?array $savedState, string $toVersion): bool
    {
        $status = $this->createStub(UpdateStatus::class);
        $status->method('load')->willReturn($savedState);

        $runner = new UpdateRunner(
            $this->createStub(UpdateCheckHelper::class),
            $status,
            $this->createStub(UpdateDownloader::class),
            $this->createStub(UpdateBackup::class),
            $this->createStub(UpdateApplier::class),
            $this->createStub(CoreMigrator::class),
            $this->createStub(Settings::class),
            $this->createStub(Config::class),
            $this->createStub(Design::class)
        );

        return (new \ReflectionMethod(UpdateRunner::class, 'resumesDeadRun'))
            ->invoke($runner, $toVersion);
    }

    public function testResumesDeadRunAllowsSameVersionNonTerminalStaleState(): void
    {
        $this->assertTrue($this->invokeResumesDeadRun([
            'step' => UpdateStatus::STEP_APPLY_FILES,
            'toVersion' => '1.1.0',
            'updatedAt' => time() - 601,
        ], '1.1.0'));
    }

    /**
     * Протухлий стан на ІНШУ версію не сміє послабити guard — інакше тег
     * v1.2.0 поверх мертвого прогону v1.1.0 отримав би резюм-виняток.
     */
    public function testResumesDeadRunRefusesADifferentTargetVersion(): void
    {
        $this->assertFalse($this->invokeResumesDeadRun([
            'step' => UpdateStatus::STEP_APPLY_FILES,
            'toVersion' => '1.1.0',
            'updatedAt' => time() - 601,
        ], '1.2.0'));
    }

    /** Термінальний стан — завершений прогін, не мертвий: резюм заборонено. */
    public function testResumesDeadRunRefusesATerminalState(): void
    {
        foreach (UpdateStatus::TERMINAL_STEPS as $terminal) {
            $this->assertFalse($this->invokeResumesDeadRun([
                'step' => $terminal,
                'toVersion' => '1.1.0',
                'updatedAt' => time() - 601,
            ], '1.1.0'), "step={$terminal}");
        }
    }

    /** Свіжий (не stale) прогін — можливо, живий процес: резюм заборонено. */
    public function testResumesDeadRunRefusesAFreshNonStaleState(): void
    {
        $this->assertFalse($this->invokeResumesDeadRun([
            'step' => UpdateStatus::STEP_APPLY_FILES,
            'toVersion' => '1.1.0',
            'updatedAt' => time(),
        ], '1.1.0'));
    }

    public function testResumesDeadRunRefusesWhenNoStateSaved(): void
    {
        $this->assertFalse($this->invokeResumesDeadRun(null, '1.1.0'));
    }
}
