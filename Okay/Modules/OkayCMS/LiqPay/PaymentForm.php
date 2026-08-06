<?php


namespace Okay\Modules\OkayCMS\LiqPay;


use Okay\Core\EntityFactory;
use Okay\Core\Modules\AbstractModule;
use Okay\Core\Modules\Interfaces\PaymentFormInterface;
use Okay\Core\Money;
use Okay\Core\Router;
use Okay\Entities\CurrenciesEntity;
use Okay\Entities\OrdersEntity;
use Okay\Entities\PaymentsEntity;

class PaymentForm extends AbstractModule implements PaymentFormInterface
{

    private $entityFactory;
    private $money;
    private $protocol;

    public function __construct(EntityFactory $entityFactory, Money $money, LiqPayProtocol $protocol)
    {
        parent::__construct();
        $this->entityFactory = $entityFactory;
        $this->money = $money;
        $this->protocol = $protocol;
    }

    /**
     * @inheritDoc
     */
    public function checkoutForm($orderId)
    {
        /** @var OrdersEntity $ordersEntity */
        $ordersEntity = $this->entityFactory->get(OrdersEntity::class);

        /** @var PaymentsEntity $paymentsEntity */
        $paymentsEntity = $this->entityFactory->get(PaymentsEntity::class);

        /** @var CurrenciesEntity $currenciesEntity */
        $currenciesEntity = $this->entityFactory->get(CurrenciesEntity::class);

        $order = $ordersEntity->get((int)$orderId);
        $liqPayOrderId = $order->id . "-" . rand(100000, 999999);
        $paymentMethod = $paymentsEntity->get($order->payment_method_id);
        $paymentCurrency = $currenciesEntity->get(intval($paymentMethod->currency_id));
        $settings = $paymentsEntity->getPaymentSettings($paymentMethod->id);

        $price = $this->money->convert($order->total_price, $paymentMethod->currency_id, false, false, 2);

        // описание заказа
        // order description
        $desc = 'Оплата заказа №'.$order->id;

        $resultUrl = Router::generateUrl('order', ['url' => $order->url], true);
        $serverUrl = Router::generateUrl('OkayCMS_LiqPay_callback', [], true);

        $privateKey = $settings['liq_pay_private_key'];
        $publicKey = $settings['liq_pay_public_key'];
        $paymentType = $settings['pay_types'];

        // Приватного ключа тут немає навмисно: у протоколі v3 він потрібен лише
        // для обчислення підпису на сервері. Раніше він лежав у цьому масиві й
        // доїжджав до браузера покупця у складі поля data.
        $data_array = [
            'action'       => 'pay',
            'amount'       => $price,
            'currency'     => $paymentCurrency->code,
            'description'  => $desc,
            'order_id'     => $liqPayOrderId,
            'result_url'   => $resultUrl,
            'server_url'   => $serverUrl,
        ];

        if(!empty($paymentType) && $paymentType !== 'default') {
            $data_array['paytypes'] = $paymentType;
        }

        $data = $this->protocol->payload($publicKey, $data_array);
        $sign = $this->protocol->sign($privateKey, $data);

        $this->design->assign('data', $data);
        $this->design->assign('sign', $sign);

        return $this->design->fetch('form.tpl');
    }
}