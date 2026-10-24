<?php

namespace Okay\Core\Security;

use Okay\Core\EntityFactory;
use Okay\Entities\ManagerSessionsEntity;
use Okay\Entities\ManagersEntity;
use Psr\Log\LoggerInterface;

/**
 * Реєстр сесій менеджерів: хто де увійшов, і відкликання будь-якої з них.
 *
 * $_SESSION['admin'] діє, лише поки в реєстрі є чинний рядок для цієї сесії
 * і цього менеджера. Бекендові входи звіряють це через enforce(), вітрина —
 * через валідатор SessionNames. Без рядка сесія не адмінська: так відкликання
 * спрацьовує на найближчому запиті з будь-якого входу.
 */
class ManagerSessions
{
    /**
     * Сесія без жодного запиту довше за це — недійсна. Те саме число
     * backend/index.php ставить у session.gc_maxlifetime: реєстр робить межу
     * точною, а не залежною від імовірнісного прибирання файлів сесій.
     */
    public const IDLE_SECONDS = 86400;
    public const TOUCH_INTERVAL_SECONDS = 60;
    public const PRUNE_AFTER_DAYS = 30;

    public function __construct(
        private EntityFactory $entityFactory,
        private ?LoggerInterface $logger = null,
        private ?ManagerAuthLog $authLog = null
    ) {
    }

    /**
     * Повний вхід у поточну бекендову сесію: новий ідентифікатор, рядок у
     * реєстрі, і лише тоді $_SESSION['admin'].
     *
     * @return bool false — сесію не записано, менеджер не увійшов
     */
    public function signIn(object $manager, bool $mfa = false): bool
    {
        // Вікно підтвердження паролем належить попередньому входу, не цьому.
        unset($_SESSION['admin'], $_SESSION[ManagerReauth::SESSION_KEY]);
        if (session_status() !== PHP_SESSION_ACTIVE || empty($manager->id) || !is_string($manager->login ?? null)) {
            return false;
        }

        SessionNames::regenerate();

        $id = $this->sessions()->add([
            'manager_id' => (int)$manager->id,
            'sid_hash'   => self::hash(session_id()),
            'ip'         => mb_substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
            'user_agent' => self::userAgent(),
            'mfa'        => $mfa ? 1 : 0,
        ]);

        if (!$id) {
            $this->logger?->error('ManagerSessions: не вдалося записати сесію менеджера ' . (int)$manager->id);

            return false;
        }

        $_SESSION['admin'] = $manager->login;
        // Токен, виданий до входу, міг бути підкинутий разом із кукою сесії.
        AdminCsrfToken::rotate();
        $this->authLog?->record(ManagerAuthLog::LOGIN, (int)$manager->id, null, ['mfa' => $mfa]);

        return true;
    }

    /**
     * Чи чинна сесія $sessionId для менеджера з логіном $login. Будь-яка
     * помилка — «ні»: краще розлогінити, ніж пустити відкликану сесію.
     */
    public function isValid(string $login, string $sessionId): bool
    {
        try {
            $row = $this->activeRow($sessionId);
            if ($row === null) {
                return false;
            }

            $manager = $this->managers()->get((int)$row->manager_id);
            if (!is_object($manager) || !is_string($manager->login ?? null) || $manager->login !== $login) {
                return false;
            }

            $this->sessions()->touch((int)$row->id, self::TOUCH_INTERVAL_SECONDS);

            return true;
        } catch (\Throwable $e) {
            $this->logger?->error('ManagerSessions: перевірка сесії впала: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Для бекендових входів, одразу після старту сесії й контейнера і до
     * першого читання $_SESSION['admin']. Нечинна адмінська сесія стає
     * порожньою сесією з новим ідентифікатором — далі вхід бачить «не
     * увійшов» і веде на сторінку входу, як для будь-якого гостя.
     */
    public function enforce(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE || !array_key_exists('admin', $_SESSION)) {
            return;
        }

        $login = $_SESSION['admin'];
        if (is_string($login) && $login !== '' && $this->isValid($login, (string)session_id())) {
            return;
        }

        $_SESSION = [];
        session_regenerate_id(true);
    }

    /**
     * Менеджер змінив собі логін: поточна сесія лишається його, а не
     * розлогінюється на наступному запиті через розбіжність логіна. Сесії
     * на інших пристроях якраз розлогінюються — там увійдуть новим логіном.
     */
    public function loginChanged(int $managerId, string $newLogin): void
    {
        $row = $this->currentRow();
        if ($row !== null && (int)$row->manager_id === $managerId && $newLogin !== '') {
            $_SESSION['admin'] = $newLogin;
        }
    }

    /** Вихід: рядок поточної сесії відкликається до знищення самої сесії. */
    public function signOut(): void
    {
        $row = $this->currentRow();
        if ($row !== null) {
            $this->sessions()->revoke((int)$row->manager_id, [(int)$row->id], null, (int)$row->manager_id);
            $this->authLog?->record(ManagerAuthLog::LOGOUT, (int)$row->manager_id);
        }
    }

    /** Рядок поточної бекендової сесії, якщо вона чинна. */
    public function currentRow(): ?object
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return null;
        }

        return $this->activeRow((string)session_id());
    }

    /** @return object[] чинні сесії менеджера, найсвіжіші першими */
    public function activeOf(int $managerId): array
    {
        $rows = $this->sessions()->noLimit()->find([
            'manager_id'    => $managerId,
            'active_within' => self::IDLE_SECONDS,
        ]);

        return is_array($rows) ? array_values($rows) : [];
    }

    /** @return object[] чинні сесії всіх менеджерів */
    public function activeOfAll(): array
    {
        $rows = $this->sessions()->noLimit()->find(['active_within' => self::IDLE_SECONDS]);

        return is_array($rows) ? array_values($rows) : [];
    }

    /** Одна сесія менеджера $ownerId. Рядок іншого менеджера не зачіпається. */
    public function revokeOne(int $ownerId, int $sessionRowId, int $byManagerId): bool
    {
        return $this->revoked($ownerId, $byManagerId, $this->sessions()->revoke($ownerId, [$sessionRowId], null, $byManagerId)) > 0;
    }

    /** Будь-яка сесія за id рядка — для власника права manager_sessions. */
    public function revokeById(int $sessionRowId, int $byManagerId): bool
    {
        $row = $sessionRowId > 0 ? $this->sessions()->get($sessionRowId) : null;
        if (!is_object($row) || (int)$row->id !== $sessionRowId) {
            return false;
        }

        return $this->revokeOne((int)$row->manager_id, $sessionRowId, $byManagerId);
    }

    /** Усі сесії менеджера, крім поточної, якщо вона його. */
    public function revokeOthers(int $managerId, int $byManagerId): int
    {
        $current = $this->currentRow();
        $except = $current !== null && (int)$current->manager_id === $managerId ? (string)$current->sid_hash : null;

        return $this->revoked($managerId, $byManagerId, $this->sessions()->revoke($managerId, null, $except, $byManagerId));
    }

    /** Усі сесії менеджера, разом із поточною. $byManagerId null — консоль. */
    public function revokeAll(int $managerId, ?int $byManagerId): int
    {
        return $this->revoked($managerId, $byManagerId, $this->sessions()->revoke($managerId, null, null, $byManagerId));
    }

    private function revoked(int $managerId, ?int $byManagerId, int $count): int
    {
        if ($count > 0) {
            $this->authLog?->record(ManagerAuthLog::SESSIONS_REVOKED, $managerId, $byManagerId !== $managerId ? $byManagerId : null, ['count' => $count]);
        }

        return $count;
    }

    /** Задача планувальника. */
    public function prune(): void
    {
        $this->sessions()->prune(self::PRUNE_AFTER_DAYS);
    }

    /**
     * «Chrome · Linux» для списку сесій. Лише підпис для людини, не
     * ідентифікація: user agent надсилає клієнт, і підробити його просто.
     */
    public static function device(string $userAgent): string
    {
        $browsers = ['Edg/' => 'Edge', 'OPR/' => 'Opera', 'Firefox/' => 'Firefox', 'Chrome/' => 'Chrome', 'Safari/' => 'Safari'];
        $systems = ['Android' => 'Android', 'iPhone' => 'iPhone', 'iPad' => 'iPad', 'Windows' => 'Windows', 'Mac OS X' => 'macOS', 'Linux' => 'Linux'];

        $browser = '';
        foreach ($browsers as $needle => $name) {
            if (str_contains($userAgent, $needle)) {
                $browser = $name;
                break;
            }
        }
        $system = '';
        foreach ($systems as $needle => $name) {
            if (str_contains($userAgent, $needle)) {
                $system = $name;
                break;
            }
        }

        $label = implode(' · ', array_filter([$browser, $system]));

        return $label !== '' ? $label : mb_substr($userAgent, 0, 40);
    }

    /** Для іконки в списку сесій; так само лише підпис, не ідентифікація. */
    public static function isMobile(string $userAgent): bool
    {
        return (bool)preg_match('~Android|iPhone|iPad|Mobile~', $userAgent);
    }

    public static function hash(string $sessionId): string
    {
        return hash('sha256', $sessionId);
    }

    private function activeRow(string $sessionId): ?object
    {
        if ($sessionId === '') {
            return null;
        }

        $hash = self::hash($sessionId);
        $row = $this->sessions()->findOne([
            'sid_hash'      => $hash,
            'active_within' => self::IDLE_SECONDS,
        ]);

        // Фільтр рушія не перевіряємо на слово: рядок мусить бути саме цієї сесії.
        if (!is_object($row) || !hash_equals($hash, (string)($row->sid_hash ?? '')) || $row->revoked_at !== null) {
            return null;
        }

        return $row;
    }

    private static function userAgent(): string
    {
        $userAgent = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');

        // Невалідний UTF-8 база відкинула б разом з усією вставкою, і вхід не відбувся б.
        return mb_substr(mb_scrub($userAgent, 'UTF-8'), 0, 255);
    }

    private function sessions(): ManagerSessionsEntity
    {
        return $this->entityFactory->get(ManagerSessionsEntity::class);
    }

    private function managers(): ManagersEntity
    {
        return $this->entityFactory->get(ManagersEntity::class);
    }
}
