<?php

namespace Okay\Modules\OkayCMS\RozetkaPay\Controllers;

use Okay\Core\Money;
use Okay\Core\Notify;
use Okay\Entities\OrdersEntity;
use Okay\Entities\PaymentsEntity;
use Okay\Controllers\AbstractController;
use Psr\Log\LoggerInterface;
use Okay\Core\QueryFactory;
class CallbackController extends AbstractController
{
    public function payOrder(
        Money $money,
        Notify $notify,
        OrdersEntity $ordersEntity,
        PaymentsEntity $paymentsEntity,
        LoggerInterface $logger,
        QueryFactory $queryFactory
    ) {
        $this->response->setContentType(RESPONSE_TEXT);
        
        $data = json_decode(file_get_contents("php://input"));

        if (!is_object($data) || empty($data->external_id) || !is_scalar($data->external_id)) {
            $this->response->setContent("Wrong data")->setStatusCode(400);
            $this->response->sendContent();
            exit;
        }

        $orderId = (string) $data->external_id;
        $order = $ordersEntity->get((int) $orderId);
        if (empty($order)) {
            $postfix = \Okay\Modules\OkayCMS\RozetkaPay\Models\Gateway\CreatePayment::POSTFIX_FOR_TEST;
            $orderId = str_replace($postfix, '', $orderId);
            $order = $ordersEntity->get((int) $orderId);
            if(empty($order)) {
                // Значення з запиту — приводимо до int, щоб воно не потрапило в лог як є.
                $logger->warning("RozetkaPay notice: 'Order not found'. Order №" . (int) $orderId);
                $this->response->setContent("Order not found")->setStatusCode(400);
                $this->response->sendContent();
                exit;
            }
        }

        $orderId = (int) $order->id;

        $createDetails = $this->getPaymentDetails($orderId, $queryFactory, OrdersEntity::getTable());

        // Прив'язка колбека до конкретного платежу тримається виключно на $createDetails->id.
        // Без нього порівняння нижче зводилось до "null !== null", тому вимагаємо його явно.
        if (empty($createDetails) || !is_object($createDetails) || !isset($createDetails->id)) {
            $logger->warning("RozetkaPay notice: 'Wrong CreatePayment data in order entity'. Order №{$orderId}");
            $this->response->setContent("Wrong CreatePayment data in order entity")->setStatusCode(400);
            $this->response->sendContent();
            exit;
        }

        $method = $paymentsEntity->get((int) $order->payment_method_id);
        if (empty($method) || $method->module !== "OkayCMS/RozetkaPay") {
            $logger->warning("RozetkaPay notice: 'Invalid payment method'. Order №{$orderId}");
            $this->response->setContent("Invalid payment method")->setStatusCode(400);
            $this->response->sendContent();
            exit;
        }

        if (!isset($data->id) || !is_scalar($data->id) || !hash_equals((string) $createDetails->id, (string) $data->id)) {
            $logger->warning("RozetkaPay notice: 'Invalid request id'. Order №{$orderId}");
            $this->response->setContent("Invalid request id")->setStatusCode(400);
            $this->response->sendContent();
            exit;
        }

        if (!isset($data->details->amount) || !is_numeric($data->details->amount)) {
            $logger->warning("RozetkaPay notice: 'Invalid total order price'. Order №{$orderId}");
            $this->response->setContent("Invalid total order price")->setStatusCode(400);
            $this->response->sendContent();
            exit;
        }

        $amount = $data->details->amount;
        $w4pAmount = round((float) $amount, 2);
        $orderAmount = $money->convert($order->total_price, $method->currency_id, false, false, 2);
        if ($orderAmount != $w4pAmount) {
            $logger->warning("RozetkaPay notice: 'Invalid total order price'. Order №{$orderId}");
            $this->response->setContent("Invalid total order price")->setStatusCode(400);
            $this->response->sendContent();
            exit;
        }

        if (!empty($data->details->status_code)
            && $data->details->status_code == 'transaction_successful'
            && !$order->paid
        ) {
            $ordersEntity->update((int) $order->id, ['paid' => 1]);
            $ordersEntity->close((int) $order->id);
            $ordersEntity->update((int)$order->id, ['payment_details' => json_encode($data)]);
            $notify->emailOrderUser((int) $order->id);
            $notify->emailOrderAdmin((int) $order->id);
        }

        $this->response->setContent(json_encode(['status' => true]), RESPONSE_JSON);
    }

    /**
     * @param $id
     * @param $queryFactory
     * @param $table
     * @return mixed
     */
    protected function getPaymentDetails($id, $queryFactory, $table)
    {
        $select = $queryFactory->newSelect();
        $data = $select->from($table)
            ->cols(['payment_details'])
            ->where('id=:id')
            ->bindValue('id', $id)
            ->results('payment_details');

        if (empty($data[0])) {
            return null;
        }

        return json_decode($data[0]);
    }
}