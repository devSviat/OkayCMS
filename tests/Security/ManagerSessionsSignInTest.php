<?php

namespace Security;

use Aura\Sql\ExtendedPdo;
use Okay\Core\Config;
use Okay\Core\EntityFactory;
use Okay\Core\Security\AdminCsrfToken;
use Okay\Core\Security\ManagerAuthLog;
use Okay\Core\Security\ManagerReauth;
use Okay\Core\Security\ManagerSessions;
use Okay\Core\ServiceLocator;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * ManagerSessions::signIn() на живій базі: новий ідентифікатор сесії, рядок у
 * реєстрі саме для нього, і лише тоді мітка admin. Вікно підтвердження паролем
 * і CSRF-токен попереднього входу його не переживають. Без бази тест пропускається.
 */
class ManagerSessionsSignInTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testSignInRotatesTheIdRegistersItAndDropsTheReauthWindow(): void
    {
        try {
            $pdo = ServiceLocator::getInstance()->getService(ExtendedPdo::class);
            $prefix = (string)ServiceLocator::getInstance()->getService(Config::class)->get('db_prefix');
            $pdo->query("SELECT 1 FROM {$prefix}manager_sessions LIMIT 1");
        } catch (\PDOException $e) {
            $this->markTestSkipped('немає бази або таблиці реєстру: ' . $e->getMessage());
        }
        $this->assertMatchesRegularExpression('/^[a-z0-9_]*$/i', $prefix);

        $pdo->beginTransaction();
        try {
            $pdo->perform("INSERT INTO {$prefix}managers (lang, login, email, password) VALUES ('en', 'si_manager', '', 'x')");
            $managerId = (int)$pdo->lastInsertId();

            session_start();
            $before = session_id();
            $_SESSION[ManagerReauth::SESSION_KEY] = time();
            $_SESSION['admin'] = 'someone_else';
            $tokenBefore = AdminCsrfToken::get();

            $log = new ManagerAuthLog(ServiceLocator::getInstance()->getService(EntityFactory::class));
            $sessions = new ManagerSessions(ServiceLocator::getInstance()->getService(EntityFactory::class), null, $log);
            $this->assertTrue($sessions->signIn((object)['id' => $managerId, 'login' => 'si_manager'], true));

            $this->assertNotSame($before, session_id(), 'фіксація сесії: ідентифікатор мусить змінитись');
            $this->assertSame('si_manager', $_SESSION['admin']);
            $this->assertArrayNotHasKey(ManagerReauth::SESSION_KEY, $_SESSION);
            $this->assertNotSame($tokenBefore, AdminCsrfToken::get(), 'CSRF-токен з-до входу не переживає вхід');
            $this->assertTrue($sessions->isValid('si_manager', session_id()));
            $this->assertFalse($sessions->isValid('si_manager', $before), 'старий ідентифікатор не зареєстровано');

            // Вхід і вихід — події журналу, вхід із позначкою, що з кодом 2FA.
            $sessions->signOut();
            $events = $pdo->fetchAll("SELECT event, details FROM {$prefix}manager_auth_log WHERE manager_id = :id ORDER BY id", ['id' => $managerId]);
            $this->assertSame([['event' => 'login', 'details' => '{"mfa":true}'], ['event' => 'logout', 'details' => '']], $events);
        } finally {
            $pdo->rollBack();
            session_destroy();
        }
    }

    /** Невдалий запис у реєстр — не вхід: мітки admin немає. */
    #[RunInSeparateProcess]
    public function testNoAdminMarkWithoutAnActiveSession(): void
    {
        $sessions = new ManagerSessions($this->createStub(EntityFactory::class));
        $_SESSION = ['admin' => 'stale'];

        $this->assertFalse($sessions->signIn((object)['id' => 1, 'login' => 'x']));
        $this->assertArrayNotHasKey('admin', $_SESSION);
    }
}
