<?php

namespace Okay\Core\Security;

use Okay\Core\EntityFactory;
use Okay\Entities\ManagerAuthLogEntity;
use Okay\Entities\ManagersEntity;
use Psr\Log\LoggerInterface;

/**
 * Журнал безпеки менеджерів: входи, невдалі спроби, 2FA, завершення сесій.
 *
 * Пишуть сервіси, що виконують дію (ManagerSessions, ManagerTwoFactor), а не
 * контролери: так у журнал потрапляє й консоль, і будь-який новий шлях до
 * тієї самої дії. Запис ніколи не кидає: журнал не має права зупинити вхід.
 */
class ManagerAuthLog
{
    public const LOGIN = 'login';
    public const LOGOUT = 'logout';
    public const PASSWORD_FAILED = 'password_failed';
    public const PASSWORD_CHANGED = 'password_changed';
    public const CODE_FAILED = 'code_failed';
    public const RECOVERY_CODE_USED = 'recovery_code_used';
    public const LIMIT = 'limit';
    public const TWO_FACTOR_ENABLED = '2fa_enabled';
    public const TWO_FACTOR_DISABLED = '2fa_disabled';
    public const RECOVERY_CODES_RENEWED = 'recovery_codes_renewed';
    public const SESSIONS_REVOKED = 'sessions_revoked';

    public const KEEP_DAYS = 90;

    private const DETAILS_MAX = 500;

    public function __construct(
        private EntityFactory $entityFactory,
        private ?LoggerInterface $logger = null
    ) {
    }

    /**
     * @param ?int $managerId чия подія; null — логін, якого немає
     * @param ?int $actorId хто виконав дію, якщо не сам менеджер; null — сам або консоль
     * @param array<string, scalar|null> $details без секретів: лише спосіб, причина, кількість
     */
    public function record(string $event, ?int $managerId, ?int $actorId = null, array $details = []): void
    {
        try {
            $id = $this->log()->add([
                'manager_id' => $managerId !== null && $managerId > 0 ? $managerId : null,
                'actor_id'   => $actorId !== null && $actorId > 0 ? $actorId : null,
                'event'      => $event,
                'ip'         => self::ip(),
                'user_agent' => self::userAgent(),
                'details'    => self::details($details),
            ]);
            if (!$id) {
                $this->logger?->error("ManagerAuthLog: подію {$event} не записано");
            }
        } catch (\Throwable $e) {
            $this->logger?->error("ManagerAuthLog: подію {$event} не записано: " . $e->getMessage());
        }
    }

    /**
     * Як record(), але не частіше разу на $withinSeconds для тієї самої події,
     * менеджера й адреси. Для подій, які зловмисник може повторювати без меж
     * (натискання понад ліміт): інакше він наповнював би таблицю сам.
     */
    public function recordOnce(string $event, ?int $managerId, int $withinSeconds, array $details = []): void
    {
        try {
            $seen = $this->log()->count([
                'event'      => $event,
                'manager_id' => $managerId !== null && $managerId > 0 ? $managerId : null,
                'ip'         => self::ip(),
                'within'     => $withinSeconds,
            ]);
            if ((int)$seen > 0) {
                return;
            }
        } catch (\Throwable $e) {
            $this->logger?->error("ManagerAuthLog: перевірка повтору {$event} впала: " . $e->getMessage());

            return;
        }

        $this->record($event, $managerId, null, $details);
    }

    /** @return object[] події менеджера, найновіші першими */
    public function of(int $managerId, int $limit = 30): array
    {
        if ($managerId <= 0) {
            return [];
        }
        $rows = $this->log()->find(['manager_id' => $managerId, 'limit' => max(1, $limit)]);

        return $this->decorate(is_array($rows) ? array_values($rows) : []);
    }

    /** @return object[] події всіх менеджерів і невідомих логінів, найновіші першими */
    public function recent(int $limit = 100): array
    {
        $rows = $this->log()->find(['limit' => max(1, $limit)]);

        return $this->decorate(is_array($rows) ? array_values($rows) : []);
    }

    /** Задача планувальника. */
    public function prune(): void
    {
        $this->log()->prune(self::KEEP_DAYS);
    }

    /**
     * Готує рядки для шаблону: розібрані details, підпис пристрою, логіни
     * менеджера й того, хто виконав дію, і тон — тривога, зміна захисту чи
     * звичайна подія.
     *
     * @param object[] $rows
     * @return object[]
     */
    private function decorate(array $rows): array
    {
        $ids = [];
        foreach ($rows as $row) {
            $ids[] = (int)$row->manager_id;
            $ids[] = (int)$row->actor_id;
        }
        $ids = array_values(array_filter(array_unique($ids)));
        $logins = [];
        if ($ids !== []) {
            foreach ($this->entityFactory->get(ManagersEntity::class)->noLimit()->find(['id' => $ids]) ?: [] as $manager) {
                $logins[(int)$manager->id] = (string)$manager->login;
            }
        }

        foreach ($rows as $row) {
            $details = json_decode((string)($row->details ?? ''), true);
            $row->details = is_array($details) ? $details : [];
            $row->device = $row->user_agent !== '' ? ManagerSessions::device((string)$row->user_agent) : '';
            $row->manager_login = $logins[(int)$row->manager_id] ?? null;
            $row->actor_login = $logins[(int)$row->actor_id] ?? null;
            $row->tone = match ((string)$row->event) {
                self::PASSWORD_FAILED, self::CODE_FAILED, self::LIMIT => 'alert',
                self::TWO_FACTOR_DISABLED, self::PASSWORD_CHANGED, self::RECOVERY_CODE_USED, self::RECOVERY_CODES_RENEWED, self::SESSIONS_REVOKED => 'change',
                default => 'info',
            };
        }

        return $rows;
    }

    private static function details(array $details): string
    {
        $details = array_filter($details, static fn ($value): bool => is_scalar($value) || $value === null);
        if ($details === []) {
            return '';
        }
        $json = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

        return is_string($json) && strlen($json) <= self::DETAILS_MAX ? $json : '';
    }

    private static function ip(): string
    {
        return mb_substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    }

    private static function userAgent(): string
    {
        // Невалідний UTF-8 база відкинула б разом з усією вставкою.
        return mb_substr(mb_scrub((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 'UTF-8'), 0, 255);
    }

    private function log(): ManagerAuthLogEntity
    {
        return $this->entityFactory->get(ManagerAuthLogEntity::class);
    }
}
