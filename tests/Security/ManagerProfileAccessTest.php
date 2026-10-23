<?php

namespace Security;

use Okay\Core\EntityFactory;
use Okay\Core\Managers;
use Okay\Core\Security\AdminLoginGuard;
use Okay\Core\Security\ManagerProfilePolicy;
use Okay\Core\Security\ManagerReauth;
use Okay\Entities\ManagersEntity;
use PHPUnit\Framework\TestCase;

/**
 * Профіль менеджера: хто який профіль обробляє (ManagerProfilePolicy) і
 * коли чутлива дія вимагає пароля (ManagerReauth).
 */
class ManagerProfileAccessTest extends TestCase
{
    private const PLAIN = ['products', 'orders'];

    protected function setUp(): void
    {
        $_SESSION = [];
    }

    private function viewer(int $id, array $permissions): object
    {
        return (object)['id' => $id, 'login' => 'viewer' . $id, 'permissions' => $permissions];
    }

    /** Без права managers ціль — завжди свій профіль, хоч би який id прийшов у формі. */
    public function testWithoutManagersRightTheTargetIsAlwaysYourself(): void
    {
        $policy = new ManagerProfilePolicy(new Managers());
        $plain = $this->viewer(7, self::PLAIN);

        $this->assertSame(7, $policy->target($plain, 1));
        $this->assertSame(7, $policy->target($plain, null), 'і створення менеджера теж неможливе');
        $this->assertSame(7, $policy->target($plain, 7));
    }

    public function testWithManagersRightAnyProfileOrANewOne(): void
    {
        $policy = new ManagerProfilePolicy(new Managers());
        $admin = $this->viewer(1, ['managers']);

        $this->assertSame(9, $policy->target($admin, 9));
        $this->assertNull($policy->target($admin, null));
        $this->assertNull($policy->target($admin, 0));
    }

    public function testOnlyOwnProfileOpensWithoutManagersRight(): void
    {
        $policy = new ManagerProfilePolicy(new Managers());
        $plain = $this->viewer(7, self::PLAIN);

        $this->assertTrue($policy->mayOpen($plain, 7));
        $this->assertFalse($policy->mayOpen($plain, 1));
        $this->assertFalse($policy->mayOpen($plain, null));
        $this->assertTrue($policy->mayOpen($this->viewer(1, ['managers']), 9));
    }

    /** Свої права не редагуються ніким, чужі — лише з правом managers. */
    public function testPermissionsAreNeverEditableOnYourOwnProfile(): void
    {
        $policy = new ManagerProfilePolicy(new Managers());

        $this->assertFalse($policy->canEditPermissions($this->viewer(1, ['managers']), 1));
        $this->assertTrue($policy->canEditPermissions($this->viewer(1, ['managers']), 2));
        $this->assertTrue($policy->canEditPermissions($this->viewer(1, ['managers']), null), 'новий менеджер');
        $this->assertFalse($policy->canEditPermissions($this->viewer(7, self::PLAIN), 2));
    }

    public function testOthersSessionsNeedTheSessionsRight(): void
    {
        $policy = new ManagerProfilePolicy(new Managers());

        $this->assertTrue($policy->canSeeSessionsOf($this->viewer(7, self::PLAIN), 7));
        $this->assertFalse($policy->canSeeSessionsOf($this->viewer(1, ['managers']), 2));
        $this->assertTrue($policy->canSeeSessionsOf($this->viewer(1, ['manager_sessions']), 2));
    }

    /** Повний набір прав (permissions = NULL у базі) дає й нове право manager_sessions. */
    public function testFullAccessManagersGetTheSessionsRight(): void
    {
        $managers = new Managers();
        $superAdmin = (object)['id' => 1, 'permissions' => null];
        $managers->setManagerPermissions($superAdmin);

        $this->assertTrue((new ManagerProfilePolicy($managers))->canSeeSessionsOf($superAdmin, 2));
    }

    /**
     * Модуль, що вішає контролер на право ядра, не дублює його: інакше права
     * менеджера зберігались як `orders,orders`, а у формі з'являвся другий
     * перемикач того самого права під назвою модуля.
     */
    public function testModuleReusingACorePermissionDoesNotDuplicateIt(): void
    {
        $managers = new Managers();
        $managers->addModulePermission('orders', 'Vendor/Module');
        $managers->addModulePermission('vendor__module', 'Vendor/Module');

        $all = $managers->getAllPermissions();
        $this->assertSame(1, count(array_keys($all, 'orders', true)));
        $this->assertContains('vendor__module', $all);
        $this->assertSame(['vendor__module' => 'Vendor/Module'], $managers->getModulesPermissions());
    }

    public function testReauthWindow(): void
    {
        $reauth = new ManagerReauth($this->createStub(AdminLoginGuard::class), $this->createStub(EntityFactory::class));

        $this->assertFalse($reauth->isFresh(), 'без підтвердження');

        $_SESSION['admin_reauth_at'] = time() - 10;
        $this->assertTrue($reauth->isFresh());

        $_SESSION['admin_reauth_at'] = time() - ManagerReauth::CONFIRM_WINDOW_SECONDS;
        $this->assertFalse($reauth->isFresh(), 'вікно закрите');

        $_SESSION['admin_reauth_at'] = time() + 3600;
        $this->assertFalse($reauth->isFresh(), 'мітка з майбутнього не продовжує вікно');

        $_SESSION['admin_reauth_at'] = (string)time();
        $this->assertFalse($reauth->isFresh(), 'лише int, як його пише checkPassword()');
    }

    public function testExpiresAtFollowsTheWindow(): void
    {
        $reauth = new ManagerReauth($this->createStub(AdminLoginGuard::class), $this->createStub(EntityFactory::class));
        $this->assertNull($reauth->expiresAt());

        $now = time();
        $_SESSION['admin_reauth_at'] = $now;
        $this->assertSame($now + ManagerReauth::CONFIRM_WINDOW_SECONDS, $reauth->expiresAt());
    }

    /** Власний пароль міняють лише з поточним, навіть у вікні. */
    public function testCheckPasswordIgnoresTheWindow(): void
    {
        $guard = $this->createMock(AdminLoginGuard::class);
        $guard->method('allowPasswordAttempt')->willReturn(true);
        $guard->expects($this->once())->method('verifyPassword')->willReturn(false);
        $_SESSION['admin_reauth_at'] = time();

        $this->assertSame(ManagerReauth::WRONG, (new ManagerReauth($guard, $this->factory()))->checkPassword(1, 'wrong', '203.0.113.7'));
    }

    public function testWrongPasswordDoesNotOpenTheWindow(): void
    {
        $guard = $this->createMock(AdminLoginGuard::class);
        $guard->method('allowPasswordAttempt')->willReturn(true);
        $guard->method('verifyPassword')->willReturn(false);
        $guard->expects($this->never())->method('passwordSucceeded');

        $this->assertSame(ManagerReauth::WRONG, (new ManagerReauth($guard, $this->factory()))->checkPassword(1, 'wrong', '203.0.113.7'));
        $this->assertArrayNotHasKey('admin_reauth_at', $_SESSION);
    }

    /** Ліміт спроб — той самий, що на вході: пароль понад ліміт не перевіряється. */
    public function testLimitStopsBeforeThePasswordIsChecked(): void
    {
        $guard = $this->createMock(AdminLoginGuard::class);
        $guard->method('allowPasswordAttempt')->willReturn(false);
        $guard->expects($this->never())->method('verifyPassword');

        $this->assertSame(ManagerReauth::LIMIT, (new ManagerReauth($guard, $this->factory()))->checkPassword(1, 'right', '203.0.113.7'));
    }

    public function testRightPasswordOpensTheWindow(): void
    {
        $guard = $this->createMock(AdminLoginGuard::class);
        $guard->method('allowPasswordAttempt')->willReturn(true);
        $guard->method('verifyPassword')->willReturn(true);
        $guard->expects($this->once())->method('passwordSucceeded')->with('viewer1', '203.0.113.7');

        $reauth = new ManagerReauth($guard, $this->factory());
        $this->assertSame(ManagerReauth::OK, $reauth->checkPassword(1, 'right', '203.0.113.7'));
        $this->assertTrue($reauth->isFresh());
    }

    /** Масив замість рядка (`reauth_password[]=`) — порожній пароль, не TypeError. */
    public function testNonStringPasswordIsTreatedAsEmpty(): void
    {
        $guard = $this->createMock(AdminLoginGuard::class);
        $guard->method('allowPasswordAttempt')->willReturn(true);
        $guard->expects($this->once())->method('verifyPassword')->with($this->anything(), '')->willReturn(false);

        $this->assertSame(ManagerReauth::WRONG, (new ManagerReauth($guard, $this->factory()))->checkPassword(1, ['x'], '203.0.113.7'));
    }

    public function testUnknownManagerIsWrongWithoutCheckingAnything(): void
    {
        $guard = $this->createMock(AdminLoginGuard::class);
        $guard->method('allowPasswordAttempt')->willReturn(true);
        $guard->expects($this->never())->method('verifyPassword');

        $this->assertSame(ManagerReauth::WRONG, (new ManagerReauth($guard, $this->factory(null)))->checkPassword(404, 'right', '203.0.113.7'));
    }

    private function factory(?object $manager = new \stdClass()): EntityFactory
    {
        if ($manager instanceof \stdClass && !isset($manager->login)) {
            $manager = (object)['id' => 1, 'login' => 'viewer1', 'password' => 'hash'];
        }
        $entity = $this->createStub(ManagersEntity::class);
        $entity->method('get')->willReturn($manager);
        $factory = $this->createStub(EntityFactory::class);
        $factory->method('get')->willReturn($entity);

        return $factory;
    }
}
