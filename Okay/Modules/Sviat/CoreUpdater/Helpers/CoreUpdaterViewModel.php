<?php

namespace Okay\Modules\Sviat\CoreUpdater\Helpers;

/**
 * Зведення снапшота перевірки (UpdateCheckHelper) і стану прогону
 * (UpdateStatus) в одну модель для сторінки адмінки — pure, без залежностей,
 * щоб контролер і майбутній .tpl не переобчислювали mode/canStartUpdate самі.
 */
class CoreUpdaterViewModel
{
    /**
     * @param ?array<string, mixed> $snapshot UpdateCheckHelper::check()/getSnapshot()
     * @param ?array<string, mixed> $runState UpdateStatus::load()
     * @param ?string $installedUpstreamBase Config::$version — апстрім-база встановленої збірки (спек §10)
     * @return array{
     *     mode: string,
     *     installed: ?string,
     *     installedUpstreamBase: ?string,
     *     latest: ?array<string, mixed>,
     *     checkedAt: ?int,
     *     lastError: ?string,
     *     run: ?array<string, mixed>,
     *     canStartUpdate: bool,
     *     canCheckNow: bool,
     *     previousRunDone: bool,
     * }
     */
    public static function build(?array $snapshot, ?array $runState, int $nowTs, ?string $installedUpstreamBase = null): array
    {
        $installed = is_string($snapshot['installed'] ?? null) ? $snapshot['installed'] : null;
        $latest = is_array($snapshot['latest'] ?? null) ? $snapshot['latest'] : null;
        $checkedAt = is_int($snapshot['checkedAt'] ?? null) ? $snapshot['checkedAt'] : null;
        $lastError = is_string($snapshot['lastError'] ?? null) ? $snapshot['lastError'] : null;
        $updateAvailable = ($snapshot['updateAvailable'] ?? null) === true;

        $step = $runState['step'] ?? null;
        $isTerminal = $runState !== null && in_array($step, UpdateStatus::TERMINAL_STEPS, true);
        $isStale = $runState !== null && !$isTerminal && UpdateStatus::isStale($runState, $nowTs);
        $previousRunDone = false;

        if ($runState !== null && !$isTerminal && !$isStale) {
            $mode = 'running';
            $canStartUpdate = false;
            $canCheckNow = false;
        } elseif ($runState !== null && !$isTerminal) {
            // stale: неперервальний крок, що не оновлювався довше
            // UpdateStatus::STALE_AFTER_SECONDS — процес гарантовано мертвий.
            $mode = 'stale_run';
            $canStartUpdate = true;
            $canCheckNow = true;
        } elseif ($isTerminal && $step === UpdateStatus::STEP_DONE && $updateAvailable) {
            // 'done' незнищенний інакше: без цієї гілки прогін-у-минулому
            // назавжди ховає кнопку старту наступного оновлення.
            $mode = 'update_available';
            $canStartUpdate = true;
            $canCheckNow = true;
            $previousRunDone = true;
        } elseif ($isTerminal) {
            /** @var string $step тут це один з UpdateStatus::TERMINAL_STEPS */
            $mode = $step;
            $canStartUpdate = $updateAvailable;
            $canCheckNow = true;
        } elseif ($snapshot === null || $snapshot === []) {
            $mode = 'no_data';
            $canStartUpdate = false;
            $canCheckNow = true;
        } elseif ($updateAvailable) {
            $mode = 'update_available';
            $canStartUpdate = true;
            $canCheckNow = true;
        } else {
            $mode = 'up_to_date';
            $canStartUpdate = false;
            $canCheckNow = true;
        }

        return [
            'mode' => $mode,
            'installed' => $installed,
            'installedUpstreamBase' => $installedUpstreamBase,
            'latest' => $latest,
            'checkedAt' => $checkedAt,
            'lastError' => $lastError,
            'run' => $runState !== null ? self::buildRun($runState) : null,
            'canStartUpdate' => $canStartUpdate,
            'canCheckNow' => $canCheckNow,
            'previousRunDone' => $previousRunDone,
        ];
    }

    /**
     * @param array<string, mixed> $runState
     * @return array<string, mixed>
     */
    private static function buildRun(array $runState): array
    {
        $step = $runState['step'] ?? null;
        $stepIndex = array_search($step, UpdateStatus::STEPS, true);

        return [
            'step' => $step,
            'stepIndex' => $stepIndex === false ? null : $stepIndex,
            'stepsTotal' => count(UpdateStatus::STEPS),
            'error' => is_string($runState['error'] ?? null) ? $runState['error'] : null,
            'rolledBackMigrations' => is_array($runState['rolledBackMigrations'] ?? null)
                ? $runState['rolledBackMigrations']
                : null,
            'migrationsDumpPath' => is_string($runState['migrationsDumpPath'] ?? null)
                ? $runState['migrationsDumpPath']
                : null,
            'backupZipPath' => is_string($runState['backupZipPath'] ?? null)
                ? $runState['backupZipPath']
                : null,
            'requiresManualIntervention' => ($runState['requiresManualIntervention'] ?? null) === true,
            'maintenanceDisabledAfterFailure' => ($runState['maintenanceDisabledAfterFailure'] ?? null) === true,
            'finalizeWarning' => is_string($runState['finalizeWarning'] ?? null)
                ? $runState['finalizeWarning']
                : null,
        ];
    }
}
