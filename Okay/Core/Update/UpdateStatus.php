<?php

namespace Okay\Core\Update;

use Okay\Core\Settings;

/**
 * Стан одного прогону оновлення ядра — для поллінгу з адмінки й для
 * виявлення обриву апдейту (спек §11). Pure-частина (advance/fail/
 * rolledBack/fresh/isStale) не має залежностей і тестується без БД;
 * save()/load() — тонкий шар над Settings під одним ключем.
 */
class UpdateStatus
{
    public const SETTING_RUN = 'core_updater__run';

    public const STEP_DOWNLOAD = 'download';
    public const STEP_VERIFY = 'verify';
    public const STEP_PREFLIGHT = 'preflight';
    public const STEP_BACKUP = 'backup';
    public const STEP_MAINTENANCE_ON = 'maintenance_on';
    public const STEP_APPLY_FILES = 'apply_files';
    public const STEP_MIGRATIONS = 'migrations';
    public const STEP_CACHE_CLEAR = 'cache_clear';
    public const STEP_HEALTH_CHECK = 'health_check';
    public const STEP_FINALIZE = 'finalize';

    /** @var list<string> робочі кроки прогону, у порядку виконання (спек §8) */
    public const STEPS = [
        self::STEP_DOWNLOAD,
        self::STEP_VERIFY,
        self::STEP_PREFLIGHT,
        self::STEP_BACKUP,
        self::STEP_MAINTENANCE_ON,
        self::STEP_APPLY_FILES,
        self::STEP_MIGRATIONS,
        self::STEP_CACHE_CLEAR,
        self::STEP_HEALTH_CHECK,
        self::STEP_FINALIZE,
    ];

    public const STEP_DONE = 'done';
    public const STEP_FAILED = 'failed';
    public const STEP_ROLLED_BACK = 'rolled_back';

    /** @var list<string> термінальні стани — з них advance() далі не веде */
    public const TERMINAL_STEPS = [self::STEP_DONE, self::STEP_FAILED, self::STEP_ROLLED_BACK];

    public const STALE_AFTER_SECONDS = 600;

    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function save(array $state): void
    {
        $this->settings->set(self::SETTING_RUN, $state);
    }

    public function load(): ?array
    {
        $state = $this->settings->get(self::SETTING_RUN);

        return is_array($state) ? $state : null;
    }

    /**
     * Позначає результат прогону показаним. Стан прогону живе в Settings
     * вічно, тож без позначки зелене «Оновлення завершено» вітало б адміна
     * на кожному відкритті сторінки — і читалось як «щойно оновились».
     */
    public function markResultSeen(array $state): void
    {
        if (isset($state['resultSeenAt'])) {
            return;
        }

        $state['resultSeenAt'] = time();
        $this->save($state);
    }

    /**
     * Початковий стан щойно створеного прогону — перший крок STEPS.
     */
    public static function fresh(string $fromVersion, string $toVersion): array
    {
        return [
            'step' => self::STEPS[0],
            'fromVersion' => $fromVersion,
            'toVersion' => $toVersion,
            'updatedAt' => time(),
        ];
    }

    /**
     * Переводить стан на наступний крок. Лише вперед: STEPS у порядку
     * оголошення, і 'done' — природне продовження після останнього кроку
     * STEPS. Регрес, повтор поточного кроку чи невідомий крок ламають
     * контракт поллінгу (клієнт очікує монотонний прогрес) — LogicException.
     */
    public static function advance(array $state, string $step, array $extra = []): array
    {
        $order = [...self::STEPS, self::STEP_DONE];

        $currentStep = $state['step'] ?? null;
        $currentIndex = array_search($currentStep, $order, true);
        if ($currentIndex === false) {
            throw new \LogicException(
                "UpdateStatus::advance: поточний крок '{$currentStep}' не є частиною STEPS"
            );
        }

        $targetIndex = array_search($step, $order, true);
        if ($targetIndex === false) {
            throw new \LogicException("UpdateStatus::advance: невідомий крок '{$step}'");
        }

        if ($targetIndex <= $currentIndex) {
            throw new \LogicException(
                "UpdateStatus::advance: '{$step}' не є прогресом від '{$currentStep}'"
            );
        }

        return array_merge($state, $extra, [
            'step' => $step,
            'updatedAt' => time(),
        ]);
    }

    /**
     * Аварійний вихід — дозволений з будь-якого кроку, на відміну від
     * advance(): падіння не мусить бути "прогресом" по STEPS.
     */
    public static function fail(array $state, string $error): array
    {
        return array_merge($state, [
            'step' => self::STEP_FAILED,
            'error' => $error,
            'updatedAt' => time(),
        ]);
    }

    public static function rolledBack(array $state, array $appliedMigrations): array
    {
        return array_merge($state, [
            'step' => self::STEP_ROLLED_BACK,
            'rolledBackMigrations' => $appliedMigrations,
            'updatedAt' => time(),
        ]);
    }

    /**
     * Термінальні стани ніколи не протухають — там нема чого доганяти.
     * Для решти — обрив апдейту, якщо крок не оновлювався довше 10 хв.
     */
    public static function isStale(array $state, int $nowTs): bool
    {
        $step = $state['step'] ?? null;
        if (in_array($step, self::TERMINAL_STEPS, true)) {
            return false;
        }

        $updatedAt = $state['updatedAt'] ?? null;
        if (!is_int($updatedAt)) {
            return true;
        }

        return ($nowTs - $updatedAt) > self::STALE_AFTER_SECONDS;
    }
}
