<?php

namespace Admin\Helpers;

use Okay\Admin\Helpers\BackendOrdersHelper;
use Okay\Core\Request;
use Okay\Entities\OrderHistoryEntity;
use Okay\Entities\OrdersEntity;
use Okay\Entities\OrderStatusEntity;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

/**
 * Картка замовлення пише перехід статусу в історію, масова дія в списку — ні.
 * На проді через це з 2026 року в історії майже не лишилось переходів у
 * «Виконаний»: його ставлять масово.
 */
class OrderStatusHistoryOnBulkChangeTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_POST = [];
    }

    public function testChangedOrdersGetAHistoryRowUnchangedDoNot(): void
    {
        $rows = $this->changeStatus(
            ['6' => 1, '7' => 5],
            newStatusId: 5,
            isClose: 0
        );

        $this->assertSame([['order_id' => 6, 'manager_id' => null, 'new_status_id' => 5]], $rows);
    }

    /** Не закрилось (не вистачило товару) — статус не змінився, і рядка бути не може. */
    public function testOrderThatFailedToCloseGetsNoHistoryRow(): void
    {
        $rows = $this->changeStatus(['6' => 1], newStatusId: 4, isClose: 1, closes: false);

        $this->assertSame([], $rows);
    }

    /**
     * @param array<string, int> $statusBefore id замовлення => статус до зміни
     * @return list<array<string, mixed>> рядки, додані в історію
     */
    private function changeStatus(array $statusBefore, int $newStatusId, int $isClose, bool $closes = true): array
    {
        // Request не мокається: у нього є метод method(). post() читає лише $_POST.
        $_POST = ['change_status_id' => (string) $newStatusId];
        $request = (new ReflectionClass(Request::class))->newInstanceWithoutConstructor();

        $statuses = $this->createStub(OrderStatusEntity::class);
        $statuses->method('findOne')->willReturn((object) ['id' => $newStatusId, 'is_close' => $isClose]);

        // mappedBy() у Entity фінальний - лишаємо справжній, він віддає той самий обʼєкт.
        $orders = $this->createStub(OrdersEntity::class);
        $orders->method('cols')->willReturnSelf();
        $orders->method('find')->willReturn(array_map(
            fn($status) => (object) ['status_id' => $status],
            $statusBefore
        ));
        $orders->method('open')->willReturn(true);
        $orders->method('close')->willReturn($closes);

        $rows = [];
        $history = $this->createStub(OrderHistoryEntity::class);
        $history->method('add')->willReturnCallback(function ($row) use (&$rows) {
            $rows[] = $row;
            return count($rows);
        });

        $helper = (new ReflectionClass(BackendOrdersHelper::class))->newInstanceWithoutConstructor();
        foreach (['request' => $request, 'orderStatusEntity' => $statuses, 'ordersEntity' => $orders, 'orderHistoryEntity' => $history] as $name => $value) {
            (new ReflectionProperty(BackendOrdersHelper::class, $name))->setValue($helper, $value);
        }

        $helper->changeStatus(array_map('intval', array_keys($statusBefore)));

        return $rows;
    }
}
