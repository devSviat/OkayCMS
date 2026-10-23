<?php

namespace Security;

use Okay\Core\Managers;
use Okay\Core\Security\AdminLoginGuard;
use Okay\Core\Security\AttemptLimiter;
use Okay\Core\Security\PasswordHasher;
use PHPUnit\Framework\TestCase;

class AdminLoginGuardTest extends TestCase
{
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
            $this->assertTrue($guard->allowPasswordAttempt('admin', '203.0.113.7'));
        }

        $this->assertFalse($guard->allowPasswordAttempt('admin', '203.0.113.7'));
        $this->assertFalse($guard->allowPasswordAttempt('ADMIN ', '203.0.113.7'), 'регістр і пробіли — той самий логін');
    }

    /**
     * Головне: чужа адреса не блокує справжнього менеджера. Раніше десять
     * хибних паролів з будь-якого місця закривали вхід на день.
     */
    public function testAttackerDoesNotLockOutTheManagerFromAnotherAddress(): void
    {
        $guard = $this->guard();
        for ($i = 0; $i < AdminLoginGuard::LOGIN_ATTEMPTS + 3; $i++) {
            $guard->allowPasswordAttempt('admin', '198.51.100.9');
        }

        $this->assertTrue($guard->allowPasswordAttempt('admin', '203.0.113.7'));
    }

    /** Перебір багатьох логінів з однієї адреси впирається в ліміт адреси. */
    public function testManyLoginsFromOneAddressHitTheAddressLimit(): void
    {
        $guard = $this->guard();
        for ($i = 0; $i < AdminLoginGuard::IP_ATTEMPTS; $i++) {
            $this->assertTrue($guard->allowPasswordAttempt('user' . $i, '198.51.100.9'));
        }

        $this->assertFalse($guard->allowPasswordAttempt('another', '198.51.100.9'));
        $this->assertTrue($guard->allowPasswordAttempt('another', '203.0.113.7'));
    }

    public function testIpv6NetworkIsOneSource(): void
    {
        $guard = $this->guard();
        for ($i = 0; $i < AdminLoginGuard::LOGIN_ATTEMPTS; $i++) {
            $guard->allowPasswordAttempt('admin', '2001:db8:1:2::' . dechex($i + 1));
        }

        $this->assertFalse($guard->allowPasswordAttempt('admin', '2001:db8:1:2::ffff'));
    }

    /** Успіх обнуляє лічильник логіна, але не адреси: інакше свій обліковий запис давав би нескінченний перебір чужих. */
    public function testSuccessResetsTheLoginCounterOnly(): void
    {
        $guard = $this->guard();
        for ($i = 0; $i < AdminLoginGuard::LOGIN_ATTEMPTS; $i++) {
            $guard->allowPasswordAttempt('admin', '203.0.113.7');
        }
        $guard->passwordSucceeded('admin', '203.0.113.7');
        $this->assertTrue($guard->allowPasswordAttempt('admin', '203.0.113.7'));

        $ipFile = $this->dir . '/' . hash('sha256', 'admin_login_ip:203.0.113.7') . '.json';
        $this->assertCount(AdminLoginGuard::LOGIN_ATTEMPTS + 1, json_decode((string)file_get_contents($ipFile), true));
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

    public function testPasswordIsCheckedOnlyForARealManager(): void
    {
        $hash = (new PasswordHasher())->hash('right');
        $guard = $this->guard();

        $this->assertTrue($guard->verifyPassword((object)['password' => $hash], 'right'));
        $this->assertFalse($guard->verifyPassword((object)['password' => $hash], 'wrong'));
        $this->assertFalse($guard->verifyPassword(null, 'right'));
        $this->assertFalse($guard->verifyPassword((object)['password' => ''], ''));
    }

    /**
     * Вигаданий логін не відповідає помітно швидше: пароль однаково хешується.
     * Межа груба (половина), бо тест не має залежати від навантаження машини.
     */
    public function testUnknownLoginTakesAsLongAsAWrongPassword(): void
    {
        $hash = (new PasswordHasher())->hash('right');
        $guard = $this->guard();

        $known = $this->elapsed(fn() => $guard->verifyPassword((object)['password' => $hash], 'wrong'));
        $unknown = $this->elapsed(fn() => $guard->verifyPassword(null, 'wrong'));

        $this->assertGreaterThan($known * 0.5, $unknown);
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
