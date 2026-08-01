<?php

namespace Admin\Helpers;

use Okay\Admin\Helpers\BackendUsersHelper;
use Okay\Core\EntityFactory;
use Okay\Entities\OrdersEntity;
use Okay\Entities\UsersEntity;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Сторінка користувача відкривається без id, коли створюють нового. Тоді
 * UsersEntity::get() віддає false, а getUser() писав у нього властивість -
 * "Attempt to assign property on false", тобто фатал на порожній формі.
 */
class BackendUsersHelperTest extends TestCase
{
    public function testMissingUserComesBackWithoutTouchingIt(): void
    {
        $helper = $this->helperFor(false, $ordersEntity);
        $ordersEntity->expects($this->never())->method('find');

        $this->assertFalse($helper->getUser(0));
    }

    public function testExistingUserGetsItsOrders(): void
    {
        $helper = $this->helperFor((object)['id' => 7], $ordersEntity);
        $ordersEntity->method('find')->willReturn(['order']);

        $user = $helper->getUser(7);

        $this->assertSame(['order'], $user->orders);
    }

    /**
     * Конструктор помічника тягне пів ядра, тож збираємо обʼєкт без нього і
     * підставляємо лише те, що читає getUser().
     */
    private function helperFor($found, &$ordersEntity): BackendUsersHelper
    {
        $usersEntity = $this->createMock(UsersEntity::class);
        $usersEntity->method('get')->willReturn($found);

        $ordersEntity = $this->createMock(OrdersEntity::class);

        $entityFactory = $this->createMock(EntityFactory::class);
        $entityFactory->method('get')->willReturn($ordersEntity);

        $helper = (new ReflectionClass(BackendUsersHelper::class))->newInstanceWithoutConstructor();
        foreach (['entityFactory' => $entityFactory, 'usersEntity' => $usersEntity] as $name => $value) {
            $property = new \ReflectionProperty(BackendUsersHelper::class, $name);
            $property->setValue($helper, $value);
        }

        return $helper;
    }
}
