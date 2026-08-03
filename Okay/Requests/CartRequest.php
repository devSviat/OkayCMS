<?php


namespace Okay\Requests;


use Okay\Core\Request;
use Okay\Core\Modules\Extender\ExtenderFacade;

class CartRequest
{
    private $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    private function text($name)
    {
        $value = $this->request->post($name);

        return is_scalar($value) ? (string)$value : '';
    }

    public function postOrder()
    {
        $order = new \stdClass;
        $order->payment_method_id = $this->request->post('payment_method_id', 'integer');
        $order->delivery_id = $this->request->post('delivery_id', 'integer');
        // Порожній рядок, а не null: name, email і comment у __orders - NOT NULL,
        // і явний null перебиває значення за замовчуванням.
        $order->name        = $this->text('name');
        $order->last_name   = $this->request->post('last_name');
        $order->email       = $this->text('email');
        $order->phone       = $this->request->post('phone');
        $order->comment     = $this->text('comment');
        // Поза HTTP-запитом (CLI, черга) ключа немає; колонка ip нульова.
        $order->ip          = $_SERVER['REMOTE_ADDR'] ?? null;

        return ExtenderFacade::execute(__METHOD__, $order, func_get_args());
    }

    public function postCoupon()
    {
        $couponCode = trim($this->request->post('coupon_code', 'string'));
        return ExtenderFacade::execute(__METHOD__, $couponCode, func_get_args());
    }
}