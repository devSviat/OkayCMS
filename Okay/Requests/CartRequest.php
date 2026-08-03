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

    /**
     * @return string значення поля, приведене до рядка; відсутнє поле дає ''
     */
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
        // Порожній рядок, а не null: name, email і comment у __orders оголошені
        // NOT NULL, і явний null перебиває їхнє значення за замовчуванням.
        // Форма теми ці поля шле завжди, тому видно це лише на клієнті, який
        // їх не шле - модуль, інтеграція, стороння тема. Замовлення тоді не
        // створюється, а слід лишається тільки в лозі.
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