<?php

namespace Okay\Modules\OkayCMS\RozetkaPay\Backend\Controllers;

use Okay\Admin\Controllers\IndexAdmin;
use Okay\Core\Request;
use Okay\Core\Security\SafeRedirect;
use Okay\Entities\OrdersEntity;
use Okay\Modules\OkayCMS\RozetkaPay\Models\Gateway\Refund;

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

    public function execute(Refund $refund)
    {
        if(isset($_GET['order'])) {
            $order = $this->getOrder($_GET['order']);
            $refundResult = $refund->refund($order);
            $ordersEntity = $this->entityFactory->get(OrdersEntity::class);
            if(isset($refundResult->is_success) && $refundResult->is_success){
                $ordersEntity->update((int) $_GET['order'], ['payment_details' => $refundResult]);
                if ($order->paid === 1) {
                    $ordersEntity->update((int) $_GET['order'], ['paid' => 0]);
                }
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