<?php

namespace Okay\Modules\OkayCMS\RozetkaPay\Backend\Controllers;

use Okay\Admin\Controllers\IndexAdmin;
use Okay\Core\Request;
use Okay\Core\Security\SafeRedirect;
use Okay\Entities\OrdersEntity;
use Okay\Entities\PaymentsEntity;
use Okay\Modules\OkayCMS\RozetkaPay\Models\Gateway\Refund;
use Psr\Log\LoggerInterface;

class RefundAdmin extends IndexAdmin
{
    public function fetch()
    {
        // Referer повністю підконтрольний відправнику запиту.
        $backUrl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        if (!SafeRedirect::isSameOrigin($backUrl, Request::getDomainWithProtocol())) {
            $backUrl = Request::getRootUrl() . '/backend/index.php';
        }
        $this->response->redirectTo($backUrl);
    }

    public function execute(Refund $refund, LoggerInterface $logger)
    {
        // Лише POST: CSRF-гард у backend/index.php перевіряє токен за методом,
        // тож на GET повернення грошей виконував будь-який сторонній запит.
        $orderId = (int) $this->request->post('order', 'integer');
        $order = $orderId > 0 ? $this->getOrder($orderId) : null;

        if (empty($order)) {
            $this->fetch();
            return;
        }

        // Гроші повертає той шлюз, яким платили: Refund::refund() бере ключі з
        // методу самого замовлення, тож чужий id ішов у розетку з чужими
        // реквізитами. Той самий гард, що й у CallbackController.
        $paymentMethod = $this->entityFactory->get(PaymentsEntity::class)
            ->get((int) $order->payment_method_id);

        if (empty($paymentMethod) || $paymentMethod->module !== 'OkayCMS/RozetkaPay') {
            $logger->warning("RozetkaPay refund: 'Invalid payment method'. Order №{$orderId}");
            $this->fetch();
            return;
        }

        $refundResult = $refund->refund($order);
        $ordersEntity = $this->entityFactory->get(OrdersEntity::class);
        if(isset($refundResult->is_success) && $refundResult->is_success){
            $ordersEntity->update($orderId, ['payment_details' => $refundResult]);
            if ($order->paid === 1) {
                $ordersEntity->update($orderId, ['paid' => 0]);
            }
        }

        $this->fetch();
    }

    private function getOrder($orderId)
    {
        /** @var OrdersEntity $ordersEntity */
        $ordersEntity = $this->entityFactory->get(OrdersEntity::class);
        $order = $ordersEntity->findOne(['id' => $orderId]);
        return $order;
    }
}