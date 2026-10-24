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
    public const PERMISSIONS_CHANGED = 'permissions_changed';
    public const LOGIN_CHANGED = 'login_changed';
    public const EMAIL_CHANGED = 'email_changed';
    public const MANAGER_CREATED = 'manager_created';
    public const MANAGER_DELETED = 'manager_deleted';

    /** Невдалі спроби показуються окремим списком: їх може насипати будь-хто, і входи та зміни захисту вони не витісняють. */
    public const FAILURES = [self::PASSWORD_FAILED, self::CODE_FAILED, self::LIMIT];

    public const KEEP_DAYS = 90;

    /**
     * Стеля подій, які може породити відвідувач без входу, на всіх разом за
     * FLOOD_WINDOW_SECONDS. Ліміти входу рахують кожну мережу окремо, і з
     * багатьох мереж таблицю інакше можна було б наповнювати без меж.
     */
    private const array FLOOD_CAPS = [self::PASSWORD_FAILED => 200, self::LIMIT => 50];
    private const int FLOOD_WINDOW_SECONDS = 900;

    private const int DETAILS_MAX = 500;

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
            if (isset(self::FLOOD_CAPS[$event])
                && (int)$this->log()->count(['event' => $event, 'within' => self::FLOOD_WINDOW_SECONDS]) >= self::FLOOD_CAPS[$event]) {
                return;
            }

            $id = $this->log()->add([
                'manager_id' => $managerId !== null && $managerId > 0 ? $managerId : null,
                'actor_id'   => $actorId !== null && $actorId > 0 ? $actorId : null,
                'event'      => $event,
                'ip'         => self::ip(),
                'ip_bucket'  => ClientIp::bucket(self::ip()),
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
     * менеджера й мережі клієнта (ClientIp::bucket, як у лімітах входу: з
     * однієї IPv6 /64 адрес мільярди). Для подій, які зловмисник може
     * повторювати без меж, — натискань понад ліміт.
     */
    public function recordOnce(string $event, ?int $managerId, int $withinSeconds, array $details = []): void
    {
        try {
            $seen = $this->log()->count([
                'event'      => $event,
                'manager_id' => $managerId !== null && $managerId > 0 ? $managerId : null,
                'ip_bucket'  => ClientIp::bucket(self::ip()),
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

    /**
     * Події менеджера, найновіші першими: входи й зміни окремо від невдалих
     * спроб, щоб другі не витісняли перші.
     *
     * @return array{events: object[], failures: object[]}
     */
    public function of(int $managerId, int $events = 30, int $failures = 20): array
    {
        if ($managerId <= 0) {
            return ['events' => [], 'failures' => []];
        }

        return $this->split(['manager_id' => $managerId], $events, $failures);
    }

    /**
     * Події всіх менеджерів і невідомих логінів, так само двома списками.
     *
     * @return array{events: object[], failures: object[]}
     */
    public function recent(int $events = 100, int $failures = 50): array
    {
        return $this->split([], $events, $failures);
    }

    /**
     * Менеджера видалено: його рядки йдуть разом із ним (id може дістатися
     * іншому — імпорт, ручний INSERT), а в загальному журналі лишається, хто
     * кого видалив.
     */
    public function forget(int $managerId, string $login, ?int $actorId): void
    {
        try {
            $this->log()->deleteOf($managerId);
        } catch (\Throwable $e) {
            $this->logger?->error('ManagerAuthLog: журнал менеджера ' . $managerId . ' не видалено: ' . $e->getMessage());
        }
        $this->record(self::MANAGER_DELETED, null, $actorId, ['login' => mb_substr($login, 0, 100)]);
    }

    /** @return array{events: object[], failures: object[]} */
    private function split(array $filter, int $events, int $failures): array
    {
        $log = $this->log();
        $main = $log->find($filter + ['not_events' => self::FAILURES, 'limit' => max(1, $events)]);
        $failed = $log->find($filter + ['events' => self::FAILURES, 'limit' => max(1, $failures)]);

        return [
            'events'   => $this->decorate(is_array($main) ? array_values($main) : []),
            'failures' => $this->decorate(is_array($failed) ? array_values($failed) : []),
        ];
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
                self::TWO_FACTOR_DISABLED, self::PASSWORD_CHANGED, self::RECOVERY_CODE_USED, self::RECOVERY_CODES_RENEWED, self::SESSIONS_REVOKED,
                self::PERMISSIONS_CHANGED, self::LOGIN_CHANGED, self::EMAIL_CHANGED, self::MANAGER_DELETED => 'change',
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
