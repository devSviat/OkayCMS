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
    /** Невдалих спроб пароля з однієї адреси — для перебору різних логінів. */
    public const IP_ATTEMPTS = 20;
    /** Спроб пароля до одного облікового запису з однієї адреси. */
    public const LOGIN_ATTEMPTS = 5;
    public const WINDOW_SECONDS = 900;
    /** Листів відновлення з однієї адреси за годину. */
    public const RECOVERY_EMAILS = 5;
    /** Листів відновлення на одну скриньку за годину — з будь-яких адрес. */
    public const RECOVERY_EMAILS_PER_RECIPIENT = 3;

    /**
     * Хеші випадкових паролів з типовими параметрами PasswordHasher::hash().
     * Перевірка проти них триває стільки ж, скільки справжня, а заздалегідь
     * обчислені вони не додають до відповіді ще й часу хешування.
     */
    private const DUMMY_ARGON2ID = '$argon2id$v=19$m=65536,t=4,p=1$L1ZjRUZSVjZEcTJWVTVIMA$qL4hfZ0aQpGYYjH9CJ0NBVTok575F8cUPYj+edllS/k';
    private const DUMMY_BCRYPT = '$2y$12$6uu9vk0ctp2j244CcVy5geq6P1iTeYnBwKVbLs26.oFu37PT/VfzS';

    private AttemptLimiter $byIp;
    private AttemptLimiter $byLogin;
    private AttemptLimiter $recovery;
    private AttemptLimiter $recoveryTo;
    private PasswordHasher $hasher;

    public function __construct(AttemptLimiter $attemptLimiter, private Managers $managers)
    {
        $this->byIp = $attemptLimiter->withLimits(self::IP_ATTEMPTS, self::WINDOW_SECONDS);
        $this->byLogin = $attemptLimiter->withLimits(self::LOGIN_ATTEMPTS, self::WINDOW_SECONDS);
        $this->recovery = $attemptLimiter->withLimits(self::RECOVERY_EMAILS, 3600);
        $this->recoveryTo = $attemptLimiter->withLimits(self::RECOVERY_EMAILS_PER_RECIPIENT, 3600);
        $this->hasher = new PasswordHasher();
    }

    /**
     * Займає спробу пароля. Рахується кожна спроба, а перевірка й запис
     * атомарні, тож паралельні запити ліміт не минають.
     *
     * @param object|null $manager знайдений за логіном. Ліміт облікового запису
     *        рахується за його id: колація `ok_managers.login` не розрізняє
     *        діакритики, і «zzsécargon» знаходить того самого менеджера
     * @return bool false — ліміт вичерпано, пароль не перевіряти
     */
    public function allowPasswordAttempt(?object $manager, string $login, string $ip): bool
    {
        return $this->byIp->attempt(self::ipKey($ip))
            && $this->byLogin->attempt(self::loginKey($manager, $login, $ip));
    }

    /**
     * Успіх обнуляє лічильник облікового запису й повертає адресі її спробу:
     * інакше офіс за одним NAT упирався б у ліміт самими лише входами. Адресу
     * не обнуляємо — свій обліковий запис не має давати нескінченного перебору
     * чужих.
     */
    public function passwordSucceeded(object $manager, string $login, string $ip): void
    {
        $this->byLogin->reset(self::loginKey($manager, $login, $ip));
        $this->byIp->refund(self::ipKey($ip));
    }

    /**
     * Час відповіді не має видавати, чи існує логін: для вигаданого, для
     * порожнього пароля й для старого швидкого хеша (APR1/MD5) виконується
     * перевірка проти фіктивного хеша поточним алгоритмом.
     */
    public function verifyPassword(?object $manager, string $password): bool
    {
        $hash = (string)($manager->password ?? '');

        if ($manager === null || $hash === '' || $password === '') {
            $this->dummyVerify($password);

            return false;
        }

        if (!$this->hasher->isModernHash($hash)) {
            $this->dummyVerify($password);
        }

        return (bool)$this->managers->checkPassword($password, $hash);
    }

    public function allowRecoveryEmail(string $ip): bool
    {
        return $this->recovery->attempt('admin_recovery:' . ClientIp::bucket($ip));
    }

    /** Ліміт на скриньку: з багатьох адрес жертву інакше можна засипати листами. */
    public function allowRecoveryEmailTo(string $email): bool
    {
        return $this->recoveryTo->attempt('admin_recovery_to:' . hash('sha256', mb_strtolower(trim($email))));
    }

    private function dummyVerify(string $password): void
    {
        password_verify($password, defined('PASSWORD_ARGON2ID') ? self::DUMMY_ARGON2ID : self::DUMMY_BCRYPT);
    }

    private static function ipKey(string $ip): string
    {
        return 'admin_login_ip:' . ClientIp::bucket($ip);
    }

    /** Без менеджера — логін без регістру й крайніх пробілів, як їх не розрізняє колація. */
    private static function loginKey(?object $manager, string $login, string $ip): string
    {
        $account = $manager !== null && !empty($manager->id)
            ? 'id:' . (int)$manager->id
            : 'login:' . mb_strtolower(trim($login));

        return 'admin_login:' . $account . '|' . ClientIp::bucket($ip);
    }
}
