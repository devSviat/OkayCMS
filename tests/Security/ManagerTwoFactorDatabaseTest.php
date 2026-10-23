<?php

namespace Security;

use Aura\Sql\ExtendedPdo;
use Okay\Core\Config;
use Okay\Core\EntityFactory;
use Okay\Core\Security\ManagerTwoFactor;
use Okay\Core\Security\Totp;
use Okay\Core\Security\TotpKey;
use Okay\Core\ServiceLocator;
use PHPUnit\Framework\TestCase;

/**
 * ManagerTwoFactor на живій MariaDB: шифрування секрету, одноразовість кроку
 * TOTP і кодів відновлення, поведінка без ключа. Усе в транзакції, що
 * відкочується; без бази тест пропускається.
 */
class ManagerTwoFactorDatabaseTest extends TestCase
{
    private const NOW = 1_800_000_000;

    private ExtendedPdo $pdo;
    private string $prefix;
    private bool $inTransaction = false;
    private int $managerId;

    protected function setUp(): void
    {
        try {
            $this->pdo = ServiceLocator::getInstance()->getService(ExtendedPdo::class);
            $this->prefix = (string)ServiceLocator::getInstance()->getService(Config::class)->get('db_prefix');
            $this->pdo->query("SELECT 1 FROM {$this->prefix}manager_totp LIMIT 1");
        } catch (\PDOException $e) {
            $this->markTestSkipped('немає бази або таблиць 2FA: ' . $e->getMessage());
        }
        $this->assertMatchesRegularExpression('/^[a-z0-9_]*$/i', $this->prefix);

        $_SESSION = [];
        $this->pdo->beginTransaction();
        $this->inTransaction = true;

        $this->pdo->perform("INSERT INTO {$this->prefix}managers (lang, login, email, password) VALUES ('en', 'tf_manager', '', 'x')");
        $this->managerId = (int)$this->pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        if ($this->inTransaction) {
            $this->pdo->rollBack();
        }
    }

    public function testEnableStoresOnlyCiphertextAndHashes(): void
    {
        [$service, $secret, $codes] = $this->enable(random_bytes(32));

        $row = $this->pdo->fetchOne("SELECT * FROM {$this->prefix}manager_totp WHERE manager_id = :id", ['id' => $this->managerId]);
        $this->assertStringNotContainsString($secret, $row['secret']);
        $this->assertStringNotContainsString(Totp::base32Encode($secret), $row['secret']);
        $this->assertCount(ManagerTwoFactor::RECOVERY_CODES, $codes);

        $hashes = $this->pdo->fetchCol("SELECT code_hash FROM {$this->prefix}manager_recovery_codes WHERE manager_id = :id", ['id' => $this->managerId]);
        $this->assertCount(ManagerTwoFactor::RECOVERY_CODES, $hashes);
        $this->assertContains(hash('sha256', str_replace('-', '', $codes[0])), $hashes);
        $this->assertNotContains(str_replace('-', '', $codes[0]), $hashes);
    }

    /** Код, прийнятий при ввімкненні, повторно не проходить; наступний крок — так. */
    public function testAStepIsAcceptedOnlyOnce(): void
    {
        [$service, $secret] = $this->enable(random_bytes(32));
        $step = Totp::step(self::NOW);

        $this->assertSame(ManagerTwoFactor::WRONG, $service->verify($this->managerId, Totp::code($secret, $step), self::NOW));
        $this->assertSame(ManagerTwoFactor::OK, $service->verify($this->managerId, Totp::code($secret, $step + 1), self::NOW + 30));
        $this->assertSame(ManagerTwoFactor::WRONG, $service->verify($this->managerId, Totp::code($secret, $step + 1), self::NOW + 30));
        $this->assertSame(ManagerTwoFactor::WRONG, $service->verify($this->managerId, Totp::code($secret, $step), self::NOW + 30), 'старіший крок після новішого');
    }

    public function testRecoveryCodeWorksOnceInAnyCase(): void
    {
        [$service, , $codes] = $this->enable(random_bytes(32));

        $this->assertSame(ManagerTwoFactor::OK_RECOVERY, $service->verify($this->managerId, strtolower($codes[3]), self::NOW));
        // Відсуваємо час використання: інакше повторний UPDATE у ту саму секунду
        // нічого не міняє, і база рахує 0 рядків навіть без умови used_at IS NULL.
        $this->pdo->perform("UPDATE {$this->prefix}manager_recovery_codes SET used_at = NOW() - INTERVAL 1 HOUR WHERE used_at IS NOT NULL");
        $this->assertSame(ManagerTwoFactor::WRONG, $service->verify($this->managerId, $codes[3], self::NOW));
        $this->assertSame(ManagerTwoFactor::RECOVERY_CODES - 1, $service->remainingRecoveryCodes($this->managerId));
    }

    /** Код відновлення одного менеджера не діє для іншого. */
    public function testRecoveryCodeIsBoundToItsManager(): void
    {
        [$service, , $codes] = $this->enable(random_bytes(32));
        $this->pdo->perform("INSERT INTO {$this->prefix}managers (lang, login, email, password) VALUES ('en', 'tf_other', '', 'x')");
        $other = (int)$this->pdo->lastInsertId();
        // Своя 2FA в іншого є: без рядка verify() відмовив би раніше, ніж дійшов до кодів.
        $this->pdo->perform("INSERT INTO {$this->prefix}manager_totp (manager_id, secret, last_step) VALUES (:id, 'x', 0)", ['id' => $other]);

        $this->assertSame(ManagerTwoFactor::WRONG, $service->verify($other, $codes[0], self::NOW));
        $this->assertSame(ManagerTwoFactor::RECOVERY_CODES, $service->remainingRecoveryCodes($this->managerId));
    }

    public function testWithoutTheKeyTotpFailsButRecoveryCodesWork(): void
    {
        $key = random_bytes(32);
        [, $secret, $codes] = $this->enable($key);
        $withoutKey = $this->service(null);

        $this->assertSame(ManagerTwoFactor::KEY_UNAVAILABLE, $withoutKey->verify($this->managerId, Totp::code($secret, Totp::step(self::NOW) + 1), self::NOW + 30));
        $this->assertSame(ManagerTwoFactor::OK_RECOVERY, $withoutKey->verify($this->managerId, $codes[1], self::NOW));
    }

    public function testAnotherKeyDoesNotDecrypt(): void
    {
        [, $secret] = $this->enable(random_bytes(32));

        $this->assertSame(
            ManagerTwoFactor::KEY_UNAVAILABLE,
            $this->service(random_bytes(32))->verify($this->managerId, Totp::code($secret, Totp::step(self::NOW) + 1), self::NOW + 30)
        );
    }

    /** Шифротекст, підкладений іншому менеджеру, не розшифровується: id — додаткові дані AEAD. */
    public function testCiphertextIsBoundToItsManager(): void
    {
        $key = random_bytes(32);
        [, $secret] = $this->enable($key);
        $this->pdo->perform("INSERT INTO {$this->prefix}managers (lang, login, email, password) VALUES ('en', 'tf_victim', '', 'x')");
        $victim = (int)$this->pdo->lastInsertId();
        $this->pdo->perform(
            "INSERT INTO {$this->prefix}manager_totp (manager_id, secret, last_step) SELECT :victim, secret, 0 FROM {$this->prefix}manager_totp WHERE manager_id = :id",
            ['victim' => $victim, 'id' => $this->managerId]
        );

        $this->assertSame(
            ManagerTwoFactor::KEY_UNAVAILABLE,
            $this->service($key)->verify($victim, Totp::code($secret, Totp::step(self::NOW) + 1), self::NOW + 30)
        );
    }

    public function testFiveWrongCodesLockEvenTheRightOne(): void
    {
        [$service, $secret] = $this->enable(random_bytes(32));
        for ($i = 0; $i < ManagerTwoFactor::CODE_ATTEMPTS; $i++) {
            $service->verify($this->managerId, '000000', self::NOW);
        }

        $this->assertSame(ManagerTwoFactor::LIMIT, $service->verify($this->managerId, Totp::code($secret, Totp::step(self::NOW) + 1), self::NOW + 30));
    }

    /** Ліміт живе в базі й зачіпає й коди відновлення. */
    public function testLimitAlsoBlocksRecoveryCodes(): void
    {
        [$service, , $codes] = $this->enable(random_bytes(32));
        $this->failAll($service, self::NOW);

        $this->assertSame(ManagerTwoFactor::LIMIT, $service->verify($this->managerId, $codes[0], self::NOW + 1));
        $this->assertSame(ManagerTwoFactor::RECOVERY_CODES, $service->remainingRecoveryCodes($this->managerId), 'код понад ліміт не згорів');
    }

    /** Кожне вичерпане вікно подвоює наступне; успіх скидає лічильник. */
    public function testEachExhaustedWindowDoublesTheNext(): void
    {
        [$service, $secret] = $this->enable(random_bytes(32));
        $window = ManagerTwoFactor::CODE_WINDOW_SECONDS;

        $this->failAll($service, self::NOW);
        $second = self::NOW + $window;
        $this->failAll($service, $second);
        $this->assertSame(ManagerTwoFactor::LIMIT, $service->verify($this->managerId, '000000', $second + $window), 'друге вікно — подвійне');

        $third = $second + 2 * $window;
        $this->assertSame(ManagerTwoFactor::OK, $service->verify($this->managerId, Totp::code($secret, Totp::step($third)), $third));
        $this->assertSame([0, 0, 0], array_map('intval', array_values($this->pdo->fetchOne(
            "SELECT failed_count, failed_since, lockouts FROM {$this->prefix}manager_totp WHERE manager_id = :id",
            ['id' => $this->managerId]
        ))));
    }

    public function testWindowGrowthStopsAtADay(): void
    {
        $this->assertSame(ManagerTwoFactor::CODE_WINDOW_SECONDS, ManagerTwoFactor::windowSeconds(0));
        $this->assertSame(2 * ManagerTwoFactor::CODE_WINDOW_SECONDS, ManagerTwoFactor::windowSeconds(1));
        $this->assertSame(ManagerTwoFactor::CODE_WINDOW_MAX_SECONDS, ManagerTwoFactor::windowSeconds(1000));
        $this->assertNull(ManagerTwoFactor::nextAttempts([ManagerTwoFactor::CODE_ATTEMPTS, self::NOW, 0], self::NOW - 3600), 'годинник назад не відкриває вікно');
    }

    /** Стан, прочитаний до чужої спроби, не записується: місце бере лише одна. */
    public function testStaleCounterDoesNotOverwrite(): void
    {
        $this->enable(random_bytes(32));
        $totp = ServiceLocator::getInstance()->getService(EntityFactory::class)->get(\Okay\Entities\ManagerTotpEntity::class);

        $this->assertTrue($totp->swapAttempts($this->managerId, [0, 0, 0], [1, self::NOW, 0]));
        $this->assertFalse($totp->swapAttempts($this->managerId, [0, 0, 0], [1, self::NOW + 1, 0]));
    }

    public function testDisableRemovesSecretAndCodes(): void
    {
        [$service, , $codes] = $this->enable(random_bytes(32));
        $service->disable($this->managerId);

        $this->assertFalse($service->isEnabled($this->managerId));
        $this->assertSame(0, $service->remainingRecoveryCodes($this->managerId));
        $this->assertSame(ManagerTwoFactor::WRONG, $service->verify($this->managerId, $codes[0], self::NOW));
    }

    /** Секрет підключення живе в сесії лише для того, хто його почав. */
    public function testSetupSecretBelongsToItsManager(): void
    {
        $service = $this->service(random_bytes(32));
        $service->startSetup($this->managerId, 'Shop', 'tf_manager');

        $this->assertNull($service->pendingSetup($this->managerId + 1, 'Shop', 'x'));
        $this->assertNull($service->confirmSetup($this->managerId + 1, '000000', self::NOW));
        $this->assertNotNull($service->pendingSetup($this->managerId, 'Shop', 'tf_manager'));
    }

    private function failAll(ManagerTwoFactor $service, int $at): void
    {
        for ($i = 0; $i < ManagerTwoFactor::CODE_ATTEMPTS; $i++) {
            $this->assertSame(ManagerTwoFactor::WRONG, $service->verify($this->managerId, '000000', $at));
        }
        $this->assertSame(ManagerTwoFactor::LIMIT, $service->verify($this->managerId, '000000', $at));
    }

    /** @return array{0: ManagerTwoFactor, 1: string, 2: string[]} */
    private function enable(string $key): array
    {
        $service = $this->service($key);
        $setup = $service->startSetup($this->managerId, 'Shop', 'tf_manager');
        $secret = (string)Totp::base32Decode($setup['secret']);
        $codes = $service->confirmSetup($this->managerId, Totp::code($secret, Totp::step(self::NOW)), self::NOW);
        $this->assertIsArray($codes, 'підключення не завершилось');

        return [$service, $secret, $codes];
    }

    private function service(?string $key): ManagerTwoFactor
    {
        $totpKey = $this->createStub(TotpKey::class);
        $totpKey->method('key')->willReturn($key);
        $totpKey->method('status')->willReturn($key === null ? TotpKey::MISSING : TotpKey::OK);

        return new ManagerTwoFactor(
            ServiceLocator::getInstance()->getService(EntityFactory::class),
            $totpKey
        );
    }
}
