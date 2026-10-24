<?php

namespace Security;

use Aura\Sql\ExtendedPdo;
use Okay\Core\Config;
use Okay\Core\Console\Commands\Manager\ManagerTwoFactorResetCommand;
use Okay\Core\EntityFactory;
use Okay\Core\Managers;
use Okay\Core\Security\AdminLoginGuard;
use Okay\Core\Security\AttemptLimiter;
use Okay\Core\Security\ManagerAuthLog;
use Okay\Core\Security\ManagerLoginKey;
use Okay\Core\Security\ManagerSessions;
use Okay\Core\Security\ManagerTwoFactor;
use Okay\Core\Security\Totp;
use Okay\Core\Security\TotpKey;
use Okay\Core\ServiceLocator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Журнал безпеки на живій MariaDB: що пишуть сервіси, хто що бачить, і що
 * зловмисник не може ним засмітити базу. Усе в транзакції, що відкочується;
 * без бази тест пропускається.
 */
class ManagerAuthLogDatabaseTest extends TestCase
{
    private const NOW = 1_800_000_000;

    private ExtendedPdo $pdo;
    private string $prefix;
    private bool $inTransaction = false;
    private int $managerId;
    private ManagerAuthLog $log;
    private array $server;

    protected function setUp(): void
    {
        try {
            $this->pdo = ServiceLocator::getInstance()->getService(ExtendedPdo::class);
            $this->prefix = (string)ServiceLocator::getInstance()->getService(Config::class)->get('db_prefix');
            $this->pdo->query("SELECT 1 FROM {$this->prefix}manager_auth_log LIMIT 1");
        } catch (\PDOException $e) {
            $this->markTestSkipped('немає бази або таблиці журналу: ' . $e->getMessage());
        }
        $this->assertMatchesRegularExpression('/^[a-z0-9_]*$/i', $this->prefix);

        $this->server = $_SERVER;
        $_SERVER['REMOTE_ADDR'] = '203.0.113.7';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (X11; Linux x86_64) Chrome/130.0';
        $_SESSION = [];
        $this->pdo->beginTransaction();
        $this->inTransaction = true;

        $this->pdo->perform("INSERT INTO {$this->prefix}managers (lang, login, email, password) VALUES ('en', 'log_manager', '', 'x')");
        $this->managerId = (int)$this->pdo->lastInsertId();
        $this->log = new ManagerAuthLog(ServiceLocator::getInstance()->getService(EntityFactory::class));
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server ?? $_SERVER;
        if ($this->inTransaction) {
            $this->pdo->rollBack();
        }
    }

    /** @return array<int, array<string, mixed>> рядки журналу менеджера, найстаріші першими */
    private function rows(?int $managerId = null): array
    {
        $managerId ??= $this->managerId;

        return $this->pdo->fetchAll("SELECT * FROM {$this->prefix}manager_auth_log WHERE manager_id = :id ORDER BY id", ['id' => $managerId]);
    }

    /** @return string[] */
    private function events(?int $managerId = null): array
    {
        return array_column($this->rows($managerId), 'event');
    }

    public function testRecordKeepsWhoWhereAndWhatButNoSecrets(): void
    {
        $this->log->record(ManagerAuthLog::TWO_FACTOR_DISABLED, $this->managerId, 99, [
            'via' => 'profile',
            'nested' => ['password' => 'secret'],
            'object' => new \stdClass(),
        ]);

        $row = $this->rows()[0];
        $this->assertSame('2fa_disabled', $row['event']);
        $this->assertSame('203.0.113.7', $row['ip']);
        $this->assertSame(99, (int)$row['actor_id']);
        $this->assertSame(['via' => 'profile'], json_decode($row['details'], true), 'лише скаляри');
    }

    /** Подія для логіна, якого немає, — без менеджера й без набраного логіна. */
    public function testUnknownLoginIsLoggedWithoutIt(): void
    {
        $before = (int)$this->pdo->fetchValue("SELECT COUNT(*) FROM {$this->prefix}manager_auth_log WHERE manager_id IS NULL");
        $this->log->record(ManagerAuthLog::PASSWORD_FAILED, null);

        $row = $this->pdo->fetchOne("SELECT * FROM {$this->prefix}manager_auth_log WHERE manager_id IS NULL ORDER BY id DESC LIMIT 1");
        $this->assertSame($before + 1, (int)$this->pdo->fetchValue("SELECT COUNT(*) FROM {$this->prefix}manager_auth_log WHERE manager_id IS NULL"));
        $this->assertSame('', $row['details']);

        // Ліміт на вигадані логіни — так само раз на вікно з адреси.
        $_SERVER['REMOTE_ADDR'] = '192.0.2.44';
        $this->log->recordOnce(ManagerAuthLog::LIMIT, null, 900);
        $this->log->recordOnce(ManagerAuthLog::LIMIT, null, 900);
        $this->assertSame(1, (int)$this->pdo->fetchValue("SELECT COUNT(*) FROM {$this->prefix}manager_auth_log WHERE manager_id IS NULL AND event = 'limit' AND ip = '192.0.2.44'"));
    }

    /**
     * Натискання понад ліміт можна повторювати без меж, тож у журнал воно
     * потрапляє раз на вікно для менеджера й адреси, а не щоразу.
     */
    public function testRepeatedLimitIsLoggedOncePerWindowAndAddress(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->log->recordOnce(ManagerAuthLog::LIMIT, $this->managerId, 900, ['stage' => 'password']);
        }
        $this->assertSame(['limit'], $this->events());

        $_SERVER['REMOTE_ADDR'] = '198.51.100.9';
        $this->log->recordOnce(ManagerAuthLog::LIMIT, $this->managerId, 900, ['stage' => 'password']);
        $this->assertSame(['limit', 'limit'], $this->events(), 'інша адреса — окремий рядок');

        $this->pdo->perform("UPDATE {$this->prefix}manager_auth_log SET created_at = NOW() - INTERVAL 1 HOUR WHERE manager_id = :id", ['id' => $this->managerId]);
        $this->log->recordOnce(ManagerAuthLog::LIMIT, $this->managerId, 900, ['stage' => 'password']);
        $this->assertCount(3, $this->events(), 'після вікна — знову');
    }

    public function testOfShowsOnlyThatManager(): void
    {
        $this->pdo->perform("INSERT INTO {$this->prefix}managers (lang, login, email, password) VALUES ('en', 'log_other', '', 'x')");
        $other = (int)$this->pdo->lastInsertId();
        $this->log->record(ManagerAuthLog::LOGIN, $this->managerId);
        $this->log->record(ManagerAuthLog::PASSWORD_FAILED, $other);
        $this->log->record(ManagerAuthLog::PASSWORD_FAILED, null);

        $mine = $this->log->of($this->managerId);
        $this->assertSame(['login'], array_map(static fn($e) => $e->event, $mine));
        $this->assertSame('log_manager', $mine[0]->manager_login);
        $this->assertSame('info', $mine[0]->tone);
        $this->assertSame('Chrome · Linux', $mine[0]->device);

        $recent = array_map(static fn($e) => [(int)$e->manager_id, $e->event], array_slice($this->log->recent(), 0, 3));
        $this->assertContains([0, 'password_failed'], $recent, 'загальний журнал бачить невідомі логіни');
        $this->assertContains([$other, 'password_failed'], $recent);
    }

    public function testPruneDropsOnlyRowsOlderThanTheRetention(): void
    {
        $this->log->record(ManagerAuthLog::LOGIN, $this->managerId);
        $this->log->record(ManagerAuthLog::LOGOUT, $this->managerId);
        $this->pdo->perform(
            "UPDATE {$this->prefix}manager_auth_log SET created_at = NOW() - INTERVAL " . (ManagerAuthLog::KEEP_DAYS + 1) . " DAY WHERE manager_id = :id AND event = 'login'",
            ['id' => $this->managerId]
        );

        $this->log->prune();

        $this->assertSame(['logout'], $this->events());
    }

    public function testTwoFactorWritesItsEvents(): void
    {
        $service = $this->twoFactor(random_bytes(32));
        $setup = $service->startSetup($this->managerId, 'Shop', 'log_manager');
        $secret = (string)Totp::base32Decode($setup['secret']);
        $codes = $service->confirmSetup($this->managerId, Totp::code($secret, Totp::step(self::NOW)), self::NOW);
        $this->assertSame(['2fa_enabled'], $this->events(), 'увімкнення не пише окремо «нові коди»');

        $service->verify($this->managerId, '000000', self::NOW);
        $service->verify($this->managerId, $codes[0], self::NOW);
        $service->regenerateRecoveryCodes($this->managerId);
        $this->assertSame(['2fa_enabled', 'code_failed', 'recovery_code_used', 'recovery_codes_renewed'], $this->events());

        $this->assertTrue($service->disable($this->managerId, 77, 'profile'));
        $last = array_slice($this->rows(), -1)[0];
        $this->assertSame(['2fa_disabled', 77, ['via' => 'profile']], [$last['event'], (int)$last['actor_id'], json_decode($last['details'], true)]);

        $this->assertFalse($service->disable($this->managerId, 77, 'profile'), 'вже вимкнена');
        $this->assertCount(5, $this->events(), 'вимкнення вимкненої не пишеться');
    }

    /** Коди понад ліміт: одна подія «ліміт» на вікно, а не на кожну спробу. */
    public function testCodeLimitIsLoggedOnce(): void
    {
        $service = $this->twoFactor(random_bytes(32));
        $setup = $service->startSetup($this->managerId, 'Shop', 'log_manager');
        $service->confirmSetup($this->managerId, Totp::code((string)Totp::base32Decode($setup['secret']), Totp::step(self::NOW)), self::NOW);

        for ($i = 0; $i < ManagerTwoFactor::CODE_ATTEMPTS + 10; $i++) {
            $service->verify($this->managerId, '000000', self::NOW);
        }

        $counts = array_count_values($this->events());
        $this->assertSame(ManagerTwoFactor::CODE_ATTEMPTS, $counts['code_failed']);
        $this->assertSame(1, $counts['limit']);
    }

    public function testSessionsWriteRevocationsWithTheActor(): void
    {
        $this->pdo->perform(
            "INSERT INTO {$this->prefix}manager_sessions (manager_id, sid_hash) VALUES (:id, :a), (:id, :b)",
            ['id' => $this->managerId, 'a' => hash('sha256', 'log-a'), 'b' => hash('sha256', 'log-b')]
        );
        $sessions = new ManagerSessions(ServiceLocator::getInstance()->getService(EntityFactory::class), null, $this->log);

        $this->assertSame(2, $sessions->revokeAll($this->managerId, 55));
        $this->assertSame(0, $sessions->revokeAll($this->managerId, 55));

        $rows = $this->rows();
        $this->assertCount(1, $rows, 'нічого не завершено — нічого не пишеться');
        $this->assertSame(['sessions_revoked', 55, ['count' => 2]], [$rows[0]['event'], (int)$rows[0]['actor_id'], json_decode($rows[0]['details'], true)]);
    }

    /** Невірний пароль пишеться і для вигаданого логіна, але без нього. */
    public function testWrongPasswordIsLoggedForKnownAndUnknownLogins(): void
    {
        $sl = ServiceLocator::getInstance();
        $guard = new AdminLoginGuard($sl->getService(AttemptLimiter::class), $sl->getService(Managers::class), $sl->getService(ManagerLoginKey::class), $this->log);
        $manager = (object)['id' => $this->managerId, 'password' => password_hash('right', PASSWORD_BCRYPT)];
        $nullsBefore = (int)$this->pdo->fetchValue("SELECT COUNT(*) FROM {$this->prefix}manager_auth_log WHERE manager_id IS NULL");

        $this->assertFalse($guard->verifyPassword($manager, 'wrong'));
        $this->assertFalse($guard->verifyPassword(null, 'wrong'));
        $this->assertTrue($guard->verifyPassword($manager, 'right'));

        $this->assertSame(['password_failed'], $this->events());
        $this->assertSame($nullsBefore + 1, (int)$this->pdo->fetchValue("SELECT COUNT(*) FROM {$this->prefix}manager_auth_log WHERE manager_id IS NULL"));
    }

    /**
     * Консольне скидання: вимикає 2FA разом із блокуванням кодів, завершує
     * сесії, пише подію «з консолі». Без 2FA — нічого не чіпає.
     */
    public function testConsoleResetDisablesTwoFactorAndEndsSessions(): void
    {
        $command = new CommandTester(new ManagerTwoFactorResetCommand());

        $this->assertSame(1, $command->execute(['login' => 'log_manager']), 'без 2FA — відмова');
        $this->assertSame(1, $command->execute(['login' => 'no_such_manager']));

        $this->pdo->perform("INSERT INTO {$this->prefix}manager_totp (manager_id, secret, last_step, failed_count, failed_since, lockouts) VALUES (:id, 'x', 0, 5, :now, 3)", ['id' => $this->managerId, 'now' => time()]);
        $this->pdo->perform("INSERT INTO {$this->prefix}manager_sessions (manager_id, sid_hash) VALUES (:id, :h)", ['id' => $this->managerId, 'h' => hash('sha256', 'log-cli')]);

        $this->assertSame(0, $command->execute(['login' => 'log_manager']));
        $this->assertStringContainsString('sessions ended: 1', $command->getDisplay());
        $this->assertSame(0, (int)$this->pdo->fetchValue("SELECT COUNT(*) FROM {$this->prefix}manager_totp WHERE manager_id = :id", ['id' => $this->managerId]));
        $this->assertSame(0, (int)$this->pdo->fetchValue("SELECT COUNT(*) FROM {$this->prefix}manager_sessions WHERE manager_id = :id AND revoked_at IS NULL", ['id' => $this->managerId]));

        $rows = array_column($this->rows(), null, 'event');
        $this->assertSame(['via' => 'cli'], json_decode($rows['2fa_disabled']['details'], true));
        $this->assertNull($rows['2fa_disabled']['actor_id']);
        $this->assertArrayHasKey('sessions_revoked', $rows);
    }

    private function twoFactor(?string $key): ManagerTwoFactor
    {
        $totpKey = $this->createStub(TotpKey::class);
        $totpKey->method('key')->willReturn($key);
        $totpKey->method('status')->willReturn($key === null ? TotpKey::MISSING : TotpKey::OK);

        return new ManagerTwoFactor(ServiceLocator::getInstance()->getService(EntityFactory::class), $totpKey, null, $this->log);
    }
}
