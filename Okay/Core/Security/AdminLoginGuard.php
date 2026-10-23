<?php

namespace Okay\Core\Security;

use Okay\Core\Managers;

/**
 * Правила входу в адмінку: ліміти спроб і перевірка пароля, що однаково
 * відповідає для існуючого й вигаданого логіна.
 *
 * Ліміт тримається на джерелі, а не на обліковому записі. Блокування
 * облікового запису давало будь-кому, хто знає логін, закрити вхід
 * справжньому менеджеру.
 */
class AdminLoginGuard
{
    /** Спроб пароля з однієї адреси — для перебору різних логінів. */
    public const IP_ATTEMPTS = 20;
    /** Спроб пароля до одного логіна з однієї адреси. */
    public const LOGIN_ATTEMPTS = 5;
    public const WINDOW_SECONDS = 900;
    /** Листів відновлення з однієї адреси за годину. */
    public const RECOVERY_EMAILS = 5;

    private AttemptLimiter $byIp;
    private AttemptLimiter $byLogin;
    private AttemptLimiter $recovery;

    public function __construct(AttemptLimiter $attemptLimiter, private Managers $managers)
    {
        $this->byIp = $attemptLimiter->withLimits(self::IP_ATTEMPTS, self::WINDOW_SECONDS);
        $this->byLogin = $attemptLimiter->withLimits(self::LOGIN_ATTEMPTS, self::WINDOW_SECONDS);
        $this->recovery = $attemptLimiter->withLimits(self::RECOVERY_EMAILS, 3600);
    }

    /**
     * Займає спробу пароля. Рахується кожна спроба, а не лише невдала:
     * перевірка й запис атомарні, тож паралельні запити ліміт не минають.
     *
     * @return bool false — ліміт вичерпано, пароль не перевіряти
     */
    public function allowPasswordAttempt(string $login, string $ip): bool
    {
        return $this->byIp->attempt(self::ipKey($ip))
            && $this->byLogin->attempt(self::loginKey($login, $ip));
    }

    public function passwordSucceeded(string $login, string $ip): void
    {
        $this->byLogin->reset(self::loginKey($login, $ip));
    }

    /**
     * Для неіснуючого менеджера пароль однаково хешується: без цього вигаданий
     * логін відповідав би помітно швидше й видавав, що такого немає.
     */
    public function verifyPassword(?object $manager, string $password): bool
    {
        if ($manager === null || empty($manager->password)) {
            (new PasswordHasher())->hash($password);

            return false;
        }

        return (bool)$this->managers->checkPassword($password, $manager->password);
    }

    public function allowRecoveryEmail(string $ip): bool
    {
        return $this->recovery->attempt('admin_recovery:' . ClientIp::bucket($ip));
    }

    private static function ipKey(string $ip): string
    {
        return 'admin_login_ip:' . ClientIp::bucket($ip);
    }

    /** Логін без регістру: колація `ok_managers.login` його не розрізняє. */
    private static function loginKey(string $login, string $ip): string
    {
        return 'admin_login:' . mb_strtolower(trim($login)) . '|' . ClientIp::bucket($ip);
    }
}
