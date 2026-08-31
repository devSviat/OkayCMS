<?php

namespace Okay\Core\Update;

/**
 * Зведення снапшота перевірки (UpdateCheckHelper) і стану прогону
 * (UpdateStatus) в одну модель для сторінки адмінки — pure, без залежностей,
 * щоб контролер і майбутній .tpl не переобчислювали mode/canStartUpdate самі.
 */
class CoreUpdaterViewModel
{
    /**
     * Стеля на випадок, якщо позначку «показано» не вдалось записати.
     * Штатно успішний результат зникає після першого ж перегляду
     * (`UpdateStatus::markResultSeen()`); це вікно лише не дає йому висіти
     * вічно, коли запис у Settings не пройшов. Невдалий прогін під вікно НЕ
     * підпадає: він вимагає дії, тож лишається видимим до наступного прогону.
     */
    public const RESULT_FRESH_SECONDS = 86400;

    /**
     * @param ?array<string, mixed> $snapshot UpdateCheckHelper::check()/getSnapshot()
     * @param ?array<string, mixed> $runState UpdateStatus::load()
     * @param ?string $installedUpstreamBase Config::$version — апстрім-база встановленої збірки (спек §10)
     * @param ?string $installedForkVersion Config::$forkVersion — запасне джерело
     *     для `installed`. Снапшот — кеш перевірки оновлень, і до першої
     *     перевірки його немає; сама ж версія збірки відома завжди й мережі
     *     не потребує, тож порожній рядок «Встановлена збірка» на свіжій
     *     інсталяції був би не станом, а артефактом джерела даних.
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
    public static function build(
        ?array $snapshot,
        ?array $runState,
        int $nowTs,
        ?string $installedUpstreamBase = null,
        ?string $installedForkVersion = null
    ): array {
        $installed = is_string($snapshot['installed'] ?? null) ? $snapshot['installed'] : $installedForkVersion;
        $latest = is_array($snapshot['latest'] ?? null) ? $snapshot['latest'] : null;
        $checkedAt = is_int($snapshot['checkedAt'] ?? null) ? $snapshot['checkedAt'] : null;
        $lastError = is_string($snapshot['lastError'] ?? null) ? $snapshot['lastError'] : null;
        $updateAvailable = ($snapshot['updateAvailable'] ?? null) === true;

        $step = $runState['step'] ?? null;
        $isTerminal = $runState !== null && in_array($step, UpdateStatus::TERMINAL_STEPS, true);
        $isStale = $runState !== null && !$isTerminal && UpdateStatus::isStale($runState, $nowTs);
        $previousRunDone = false;

        $updatedAt = is_int($runState['updatedAt'] ?? null) ? $runState['updatedAt'] : null;
        $resultIsFresh = $updatedAt !== null && ($nowTs - $updatedAt) < self::RESULT_FRESH_SECONDS;
        $resultSeen = isset($runState['resultSeenAt']);

        // Успішний результат — новина одного перегляду: далі сторінка
        // говорить про поточний стан, а не про те, що колись усе вдалося.
        $staleSuccess = $isTerminal
            && $step === UpdateStatus::STEP_DONE
            && ($resultSeen || !$resultIsFresh);

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
            $previousRunDone = !$staleSuccess;
        } elseif ($isTerminal && !$staleSuccess) {
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
            'updatedAt' => is_int($runState['updatedAt'] ?? null) ? $runState['updatedAt'] : null,
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
