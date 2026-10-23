<?php

namespace Security;

use Okay\Core\Managers;
use Okay\Core\Security\AdminLoginGuard;
use Okay\Core\Security\AttemptLimiter;
use Okay\Core\Security\PasswordHasher;
use PHPUnit\Framework\TestCase;

class AdminLoginGuardTest extends TestCase
{
    /** `openssl passwd -apr1 -salt 8qRm7Tz2 legacy-pass` */
    private const APR1_LEGACY_PASS = '$apr1$8qRm7Tz2$etS2K8gZXdeJnQib2fy0B0';

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/okay-login-guard-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);
    }

    /** Перебір одного логіна з однієї адреси зупиняється, і відмова не рахує нових спроб. */
    public function testOneLoginFromOneAddressIsLimited(): void
    {
        $guard = $this->guard();
        for ($i = 0; $i < AdminLoginGuard::LOGIN_ATTEMPTS; $i++) {
            $this->assertTrue($guard->allowPasswordAttempt(null, 'admin', '203.0.113.7'));
        }

        $this->assertFalse($guard->allowPasswordAttempt(null, 'admin', '203.0.113.7'));
        $this->assertFalse($guard->allowPasswordAttempt(null, 'ADMIN ', '203.0.113.7'), 'регістр і пробіли — той самий логін');
    }

    /**
     * Головне: чужа адреса не блокує справжнього менеджера. Раніше десять
     * хибних паролів з будь-якого місця закривали вхід на день.
     */
    public function testAttackerDoesNotLockOutTheManagerFromAnotherAddress(): void
    {
        $guard = $this->guard();
        for ($i = 0; $i < AdminLoginGuard::LOGIN_ATTEMPTS + 3; $i++) {
            $guard->allowPasswordAttempt(null, 'admin', '198.51.100.9');
        }

        $this->assertTrue($guard->allowPasswordAttempt(null, 'admin', '203.0.113.7'));
    }

    /** Перебір багатьох логінів з однієї адреси впирається в ліміт адреси. */
    public function testManyLoginsFromOneAddressHitTheAddressLimit(): void
    {
        $guard = $this->guard();
        for ($i = 0; $i < AdminLoginGuard::IP_ATTEMPTS; $i++) {
            $this->assertTrue($guard->allowPasswordAttempt(null, 'user' . $i, '198.51.100.9'));
        }

        $this->assertFalse($guard->allowPasswordAttempt(null, 'another', '198.51.100.9'));
        $this->assertTrue($guard->allowPasswordAttempt(null, 'another', '203.0.113.7'));
    }

    public function testIpv6NetworkIsOneSource(): void
    {
        $guard = $this->guard();
        for ($i = 0; $i < AdminLoginGuard::LOGIN_ATTEMPTS; $i++) {
            $guard->allowPasswordAttempt(null, 'admin', '2001:db8:1:2::' . dechex($i + 1));
        }

        $this->assertFalse($guard->allowPasswordAttempt(null, 'admin', '2001:db8:1:2::ffff'));
    }

    /**
     * Успіх обнуляє лічильник облікового запису й повертає адресі одну спробу,
     * але не обнуляє адресу: інакше свій обліковий запис давав би нескінченний
     * перебір чужих.
     */
    public function testSuccessResetsTheAccountAndRefundsOneAddressAttempt(): void
    {
        $guard = $this->guard();
        $manager = (object)['id' => 7];
        for ($i = 0; $i < AdminLoginGuard::LOGIN_ATTEMPTS; $i++) {
            $guard->allowPasswordAttempt($manager, 'admin', '203.0.113.7');
        }
        $guard->passwordSucceeded($manager, 'admin', '203.0.113.7');
        $this->assertTrue($guard->allowPasswordAttempt($manager, 'admin', '203.0.113.7'));

        $ipFile = $this->dir . '/' . hash('sha256', 'admin_login_ip:203.0.113.7') . '.json';
        $this->assertCount(AdminLoginGuard::LOGIN_ATTEMPTS, json_decode((string)file_get_contents($ipFile), true));
    }

    /** Офіс за одним NAT: самі лише успішні входи ліміт адреси не вичерпують. */
    public function testSuccessfulLoginsDoNotUseUpTheAddressLimit(): void
    {
        $guard = $this->guard();
        for ($i = 0; $i < AdminLoginGuard::IP_ATTEMPTS * 2; $i++) {
            $manager = (object)['id' => $i + 1];
            $this->assertTrue($guard->allowPasswordAttempt($manager, 'm' . $i, '203.0.113.7'));
            $guard->passwordSucceeded($manager, 'm' . $i, '203.0.113.7');
        }
    }

    /**
     * Колація `ok_managers.login` не розрізняє діакритики: «zzsécargon» знаходить
     * того самого менеджера, тож ліміт рахується за id, а не за рядком.
     */
    public function testAccountLimitFollowsTheManagerNotTheSpelling(): void
    {
        $guard = $this->guard();
        $manager = (object)['id' => 7];
        for ($i = 0; $i < AdminLoginGuard::LOGIN_ATTEMPTS; $i++) {
            $guard->allowPasswordAttempt($manager, 'zzsecargon', '203.0.113.7');
        }

        $this->assertFalse($guard->allowPasswordAttempt($manager, 'zzsécargon', '203.0.113.7'));
    }

    public function testRecoveryEmailsAreLimitedPerAddress(): void
    {
        $guard = $this->guard();
        for ($i = 0; $i < AdminLoginGuard::RECOVERY_EMAILS; $i++) {
            $this->assertTrue($guard->allowRecoveryEmail('198.51.100.9'));
        }

        $this->assertFalse($guard->allowRecoveryEmail('198.51.100.9'));
        $this->assertTrue($guard->allowRecoveryEmail('203.0.113.7'));
    }

    public function testRecoveryEmailsAreLimitedPerRecipientFromAnyAddress(): void
    {
        $guard = $this->guard();
        for ($i = 0; $i < AdminLoginGuard::RECOVERY_EMAILS_PER_RECIPIENT; $i++) {
            $this->assertTrue($guard->allowRecoveryEmailTo('Victim@Example.com'));
        }

        $this->assertFalse($guard->allowRecoveryEmailTo(' victim@example.com'));
        $this->assertTrue($guard->allowRecoveryEmailTo('other@example.com'));
    }

    public function testPasswordIsCheckedOnlyForARealManager(): void
    {
        $hash = (new PasswordHasher())->hash('right');
        $guard = $this->guard();

        $this->assertTrue($guard->verifyPassword((object)['password' => $hash], 'right'));
        $this->assertFalse($guard->verifyPassword((object)['password' => $hash], 'wrong'));
        $this->assertFalse($guard->verifyPassword((object)['password' => $hash], ''));
        $this->assertFalse($guard->verifyPassword(null, 'right'));
        $this->assertFalse($guard->verifyPassword((object)['password' => ''], ''));
    }

    /** Старий хеш (APR1) і далі пускає: вирівнювання часу не ламає перевірку. */
    public function testLegacyHashStillVerifies(): void
    {
        $guard = $this->guard();
        $legacy = (object)['password' => self::APR1_LEGACY_PASS];

        $this->assertTrue($guard->verifyPassword($legacy, 'legacy-pass'));
        $this->assertFalse($guard->verifyPassword($legacy, 'not-it'));
    }

    /**
     * Час відповіді не видає, чи існує логін: вигаданий логін, порожній пароль і
     * старий швидкий хеш тривають стільки ж, скільки хибний пароль до сучасного
     * хеша. В обидва боки, з грубою межею: тест не має залежати від навантаження.
     */
    public function testTimingDoesNotRevealTheAccount(): void
    {
        $guard = $this->guard();
        $modern = (object)['password' => (new PasswordHasher())->hash('right')];
        $legacy = (object)['password' => self::APR1_LEGACY_PASS];

        $reference = $this->elapsed(fn() => $guard->verifyPassword($modern, 'wrong'));
        $cases = [
            'вигаданий логін' => fn() => $guard->verifyPassword(null, 'wrong'),
            'вигаданий логін, порожній пароль' => fn() => $guard->verifyPassword(null, ''),
            'існуючий логін, порожній пароль' => fn() => $guard->verifyPassword($modern, ''),
            'старий хеш APR1' => fn() => $guard->verifyPassword($legacy, 'wrong'),
        ];

        foreach ($cases as $name => $case) {
            $time = $this->elapsed($case);
            $this->assertGreaterThan($reference * 0.5, $time, $name . ': помітно швидше');
            $this->assertLessThan($reference * 2.5, $time, $name . ': помітно довше');
        }
    }

    private function elapsed(callable $call): float
    {
        $best = INF;
        for ($i = 0; $i < 3; $i++) {
            $start = hrtime(true);
            $call();
            $best = min($best, hrtime(true) - $start);
        }

        return $best;
    }

    private function guard(): AdminLoginGuard
    {
        return new AdminLoginGuard(new AttemptLimiter($this->dir, 10, 300), new Managers());
    }
}
