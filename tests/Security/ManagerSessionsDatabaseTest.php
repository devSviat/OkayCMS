<?php

namespace Security;

use Aura\Sql\ExtendedPdo;
use Okay\Core\Config;
use Okay\Core\EntityFactory;
use Okay\Core\Security\ManagerSessions;
use Okay\Core\ServiceLocator;
use Okay\Entities\ManagerSessionsEntity;
use PHPUnit\Framework\TestCase;

/**
 * Властивості реєстру сесій на живій MariaDB: чи сесія чинна, чиї рядки
 * зачіпає відкликання, межа бездіяльності. Мок бази не бачить ні часу бази,
 * ні того, що фільтр справді потрапив у запит.
 *
 * Усе в транзакції, що відкочується; без бази тест пропускається.
 */
class ManagerSessionsDatabaseTest extends TestCase
{
    private ExtendedPdo $pdo;
    private ManagerSessions $sessions;
    private string $prefix;
    private int $alice;
    private int $bob;

    protected function setUp(): void
    {
        try {
            $this->pdo = ServiceLocator::getInstance()->getService(ExtendedPdo::class);
            $this->pdo->query('SELECT 1 FROM ' . $this->table('manager_sessions') . ' LIMIT 1');
        } catch (\PDOException $e) {
            $this->markTestSkipped('немає бази або таблиці реєстру: ' . $e->getMessage());
        }

        $this->sessions = new ManagerSessions(ServiceLocator::getInstance()->getService(EntityFactory::class));
        $this->pdo->beginTransaction();

        $this->alice = $this->manager('rs_alice');
        $this->bob = $this->manager('rs_bob');
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo) && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function testFreshSessionIsValidOnlyForItsOwnLogin(): void
    {
        $this->row($this->alice, 'sid-alice');

        $this->assertTrue($this->sessions->isValid('rs_alice', 'sid-alice'));
        $this->assertFalse($this->sessions->isValid('rs_bob', 'sid-alice'), 'чужий логін із тією ж сесією');
        $this->assertFalse($this->sessions->isValid('RS_ALICE', 'sid-alice'), 'логін порівнюється точно, не за колацією');
        $this->assertFalse($this->sessions->isValid('rs_alice', 'sid-unknown'));
        $this->assertFalse($this->sessions->isValid('rs_alice', ''));
    }

    public function testRevokedSessionIsInvalid(): void
    {
        $id = $this->row($this->alice, 'sid-alice');
        $this->assertTrue($this->sessions->revokeOne($this->alice, $id, $this->alice));

        $this->assertFalse($this->sessions->isValid('rs_alice', 'sid-alice'));
    }

    public function testIdleSessionIsInvalid(): void
    {
        $this->row($this->alice, 'sid-idle', 'NOW() - INTERVAL ' . (ManagerSessions::IDLE_SECONDS + 60) . ' SECOND');
        $this->row($this->alice, 'sid-recent', 'NOW() - INTERVAL ' . (ManagerSessions::IDLE_SECONDS - 60) . ' SECOND');

        $this->assertFalse($this->sessions->isValid('rs_alice', 'sid-idle'));
        $this->assertTrue($this->sessions->isValid('rs_alice', 'sid-recent'));
    }

    /** Список сесій у профілі й огляді показує лише чинні. */
    public function testActiveListsHideRevokedAndIdleSessions(): void
    {
        $live = $this->row($this->alice, 'sid-live');
        $revoked = $this->row($this->alice, 'sid-revoked');
        $this->sessions->revokeOne($this->alice, $revoked, $this->alice);
        $this->row($this->alice, 'sid-idle', 'NOW() - INTERVAL ' . (ManagerSessions::IDLE_SECONDS + 60) . ' SECOND');
        $bob = $this->row($this->bob, 'sid-bob');

        $this->assertSame([$live], array_map(static fn($r) => (int)$r->id, $this->sessions->activeOf($this->alice)));

        $all = array_map(static fn($r) => (int)$r->id, $this->sessions->activeOfAll());
        $this->assertContains($live, $all);
        $this->assertContains($bob, $all);
        $this->assertNotContains($revoked, $all);
    }

    /** Рядок іншого менеджера не завершується, навіть якщо підставити його id. */
    public function testRevokeOneTouchesOnlyTheOwnersRows(): void
    {
        $bobRow = $this->row($this->bob, 'sid-bob');

        $this->assertFalse($this->sessions->revokeOne($this->alice, $bobRow, $this->alice));
        $this->assertTrue($this->sessions->isValid('rs_bob', 'sid-bob'));
    }

    public function testRevokeAllEndsEveryRowOfThatManagerOnly(): void
    {
        $this->row($this->alice, 'sid-a1');
        $this->row($this->alice, 'sid-a2');
        $this->row($this->bob, 'sid-b1');

        $this->assertSame(2, $this->sessions->revokeAll($this->alice, $this->bob));
        $this->assertFalse($this->sessions->isValid('rs_alice', 'sid-a1'));
        $this->assertFalse($this->sessions->isValid('rs_alice', 'sid-a2'));
        $this->assertTrue($this->sessions->isValid('rs_bob', 'sid-b1'));
    }

    public function testRevokeByIdFindsTheOwnerItself(): void
    {
        $bobRow = $this->row($this->bob, 'sid-bob');

        $this->assertTrue($this->sessions->revokeById($bobRow, $this->alice));
        $this->assertFalse($this->sessions->isValid('rs_bob', 'sid-bob'));
        $this->assertFalse($this->sessions->revokeById(0, $this->alice));
    }

    /** Видалений менеджер — сесія недійсна, навіть з рядком у реєстрі. */
    public function testSessionOfADeletedManagerIsInvalid(): void
    {
        $this->row($this->bob, 'sid-bob');
        $this->pdo->perform('DELETE FROM ' . $this->table('managers') . ' WHERE id = :id', ['id' => $this->bob]);

        $this->assertFalse($this->sessions->isValid('rs_bob', 'sid-bob'));
    }

    public function testLastSeenIsTouchedAtMostOncePerInterval(): void
    {
        $fresh = $this->row($this->alice, 'sid-fresh', 'NOW() - INTERVAL 10 SECOND');
        $stale = $this->row($this->alice, 'sid-stale', 'NOW() - INTERVAL ' . (ManagerSessions::TOUCH_INTERVAL_SECONDS + 30) . ' SECOND');

        $this->sessions->isValid('rs_alice', 'sid-fresh');
        $this->sessions->isValid('rs_alice', 'sid-stale');

        $this->assertGreaterThanOrEqual(5, $this->secondsSinceSeen($fresh), 'свіжий рядок не переписується');
        $this->assertLessThan(5, $this->secondsSinceSeen($stale), 'старий рядок оновлено');
    }

    public function testPruneDropsOnlyOldRows(): void
    {
        $days = ManagerSessions::PRUNE_AFTER_DAYS + 1;
        $old = $this->row($this->alice, 'sid-old', "NOW() - INTERVAL {$days} DAY");
        $recent = $this->row($this->alice, 'sid-recent');

        $this->sessions->prune();

        $ids = $this->pdo->fetchCol('SELECT id FROM ' . $this->table('manager_sessions') . ' WHERE id IN (:ids)', ['ids' => [$old, $recent]]);
        $this->assertSame([(string)$recent], array_map('strval', $ids));
    }

    private function manager(string $login): int
    {
        $this->pdo->perform(
            'INSERT INTO ' . $this->table('managers') . " (lang, login, email, password) VALUES ('en', :login, '', 'x')",
            ['login' => $login]
        );

        return (int)$this->pdo->lastInsertId();
    }

    private function row(int $managerId, string $sessionId, string $lastSeen = 'NOW()'): int
    {
        $this->pdo->perform(
            'INSERT INTO ' . $this->table('manager_sessions')
            . " (manager_id, sid_hash, created_at, last_seen_at) VALUES (:m, :h, {$lastSeen}, {$lastSeen})",
            ['m' => $managerId, 'h' => ManagerSessions::hash($sessionId)]
        );

        return (int)$this->pdo->lastInsertId();
    }

    private function secondsSinceSeen(int $id): int
    {
        return (int)$this->pdo->fetchValue(
            'SELECT TIMESTAMPDIFF(SECOND, last_seen_at, NOW()) FROM ' . $this->table('manager_sessions') . ' WHERE id = :id',
            ['id' => $id]
        );
    }

    private function table(string $name): string
    {
        $this->prefix ??= (string)ServiceLocator::getInstance()->getService(Config::class)->get('db_prefix');
        if (!preg_match('/^[a-z0-9_]*$/i', $this->prefix)) {
            $this->fail('підозрілий префікс таблиць');
        }

        return $this->prefix . $name;
    }
}
