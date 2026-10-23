<?php

namespace Okay\Core\Security;

use Okay\Entities\ManagersEntity;
use Okay\Core\EntityFactory;

/**
 * Підтвердження паролем перед чутливою дією в адмінці: зміна пароля, email,
 * логіна, прав, завершення чужих сесій. Сама кука сесії цього не дає, тож
 * викрадена сесія не перетворюється на захоплення облікового запису.
 *
 * Підтвердження відкриває вікно на CONFIRM_WINDOW_SECONDS (мітка в серверній
 * сесії), у ньому чутливі дії виконуються без пароля. Спроби рахує той самий
 * ліміт, що й вхід.
 */
class ManagerReauth
{
    public const CONFIRM_WINDOW_SECONDS = 600;

    public const OK = 'ok';
    public const WRONG = 'password_wrong';
    public const LIMIT = 'limit_try';
    /** Дію спробували без відкритого вікна. */
    public const REQUIRED = 'reauth_required';

    public const SESSION_KEY = 'admin_reauth_at';

    public function __construct(
        private AdminLoginGuard $loginGuard,
        private EntityFactory $entityFactory
    ) {
    }

    public function isFresh(): bool
    {
        $at = $_SESSION[self::SESSION_KEY] ?? null;

        return is_int($at) && $at <= time() && time() - $at < self::CONFIRM_WINDOW_SECONDS;
    }

    /** До якого часу відкрите вікно, або null. */
    public function expiresAt(): ?int
    {
        return $this->isFresh() ? (int)$_SESSION[self::SESSION_KEY] + self::CONFIRM_WINDOW_SECONDS : null;
    }

    /**
     * Перевіряє пароль менеджера $managerId і відкриває вікно. Вікна не
     * враховує: так перевіряється й поточний пароль при зміні власного.
     *
     * @return string self::OK | self::WRONG | self::LIMIT
     */
    public function checkPassword(int $managerId, mixed $password, string $ip): string
    {
        $manager = $this->entityFactory->get(ManagersEntity::class)->get($managerId);
        $login = is_object($manager) ? (string)$manager->login : '';
        $password = is_string($password) ? $password : '';

        if (!$this->loginGuard->allowPasswordAttempt($login, $ip)) {
            return self::LIMIT;
        }

        if ($login === '' || !$this->loginGuard->verifyPassword($manager, $password)) {
            return self::WRONG;
        }

        $this->loginGuard->passwordSucceeded($login, $ip);
        $_SESSION[self::SESSION_KEY] = time();

        return self::OK;
    }
}
