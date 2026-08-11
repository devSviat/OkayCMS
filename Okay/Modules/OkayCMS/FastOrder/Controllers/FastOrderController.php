<?php


namespace Okay\Modules\OkayCMS\FastOrder\Controllers;


use Okay\Core\Cart;
use Okay\Core\FrontTranslations;
use Okay\Core\Notify;
use Okay\Core\Phone;
use Okay\Core\Router;
use Okay\Core\Languages;
use Okay\Core\Security\FormToken;
use Okay\Core\EntityFactory;
use Okay\Core\Validator;
use Okay\Entities\VariantsEntity;
use Okay\Helpers\CartHelper;
use Okay\Helpers\OrdersHelper;
use Okay\Entities\OrdersEntity;
use Okay\Entities\PurchasesEntity;
use Okay\Controllers\AbstractController;
use Okay\Modules\OkayCMS\FastOrder\Extenders\BackendExtender;
use Okay\Modules\OkayCMS\FastOrder\Helpers\ValidateHelper;

class FastOrderController extends AbstractController
{
    /** Форма швидкого замовлення для FormToken. */
    const FAST_ORDER_FORM = 'fast_order';

    public function createOrder(
        EntityFactory     $entityFactory,
        OrdersHelper      $ordersHelper,
        Languages         $languages,
        Notify            $notify,
        Validator         $validator,
        FrontTranslations $frontTranslations,
        CartHelper        $cartHelper,
        VariantsEntity    $variantsEntity,
        Cart              $cart,
        BackendExtender   $validateExtend
    ) {
        // Форму шле ajax, тож і відмова має бути JSON: fast_order.js читає
        // відповідь як json і без цього просто нічого не показав би.
        $this->requireCustomerCsrf(true);

        $order = new \stdClass();
        $order->name    = $this->request->post('name');
        $order->last_name = $this->request->post('last_name');
        $order->phone   = Phone::toSave($this->request->post('phone'));
        $order->email   = '';
        $order->comment = $frontTranslations->getTranslation('fast_order');
        $order->lang_id = $languages->getLangId();
        $order->ip      = $_SERVER['REMOTE_ADDR'];
        $variantId = $this->request->post('variant_id');

        $order = $ordersHelper->attachUserIfLogin($order, $this->user);

        $errors = $validateExtend->ValidateFastOrder($order,$variantId);

        if (!empty($errors)) {
            return $this->response->setContent(json_encode(['errors' => $errors]), RESPONSE_JSON);
        }

        $token = $this->request->post('form_token');

        if (!$this->acceptFastOrder($order, $variantId)) {
            // Замовлення вже створене цим токеном: другий клік має привести
            // туди ж, куди привів перший.
            if (($created = FormToken::recall(self::FAST_ORDER_FORM, $token)) !== null) {
                return $this->response->setContent(json_encode([
                    'success'           => 1,
                    'redirect_location' => Router::generateUrl('order', ['url' => $created], true),
                ], JSON_UNESCAPED_SLASHES), RESPONSE_JSON);
            }

            // Токен витрачений, але замовлення за ним немає: попередню спробу
            // обірвало вже після зняття токена. Це не повтор, і глухий кут із
            // порадою «спробуйте ще раз» тут нічим би не скінчився - тож
            // пропускаємо. Коли токена немає зовсім, рішення лишається за
            // відбитком, і тоді це справді повтор.
            if (!FormToken::isWellFormed($token)) {
                return $this->response->setContent(json_encode([
                    'errors' => [$frontTranslations->getTranslation('okay_cms__fast_order__resend_error')],
                ]), RESPONSE_JSON);
            }
        }

        /** @var OrdersEntity $ordersEntity */
        $ordersEntity = $entityFactory->get(OrdersEntity::class);
        $preparedOrder = $ordersHelper->prepareAdd($order);
        $orderId       = $ordersEntity->add($preparedOrder);

        $amount = $this->request->post('amount', 'integer');
        if ($amount <= 0) {
            $amount = 1;
        }

        // Покупка в один клік - це один товар, а не весь кошик. Раніше варіант
        // дописувався в живий кошик, звідти потрапляв у замовлення разом з
        // усім, що покупець ще обирав, і кошик по тому очищався. getPurchases()
        // рахує склад замовлення з переданого списку й $_SESSION не торкається.
        $cart->getPurchases([$variantId => $amount]);

        $preparedCart = $cartHelper->prepareCart($cart->get(), $orderId);
        $preparedCart = $cartHelper->cartToOrder($preparedCart, $orderId);
        $preparedCart = $cartHelper->prepareDiscounts($preparedCart, $orderId);
        $cartHelper->discountsToDB($preparedCart);

        $order = $ordersEntity->findOne(['id' => $orderId]);
        $ordersEntity->updateTotalPrice($orderId);
        $ordersHelper->finalCreateOrderProcedure($order);

        $notify->emailOrderUser($order->id);
        $notify->emailOrderAdmin($order->id);

        $orderUrl = $order->url;
        FormToken::remember(self::FAST_ORDER_FORM, $token, $orderUrl);

        return $this->response->setContent(json_encode([
            'success'           => 1,
            'redirect_location' => Router::generateUrl('order', ['url' => $orderUrl], true)
        ]), RESPONSE_JSON);
    }

    private function acceptFastOrder($order, $variantId)
    {
        return FormToken::accept(
            self::FAST_ORDER_FORM,
            $this->request->post('form_token'),
            [$order, $variantId]
        );
    }
}
