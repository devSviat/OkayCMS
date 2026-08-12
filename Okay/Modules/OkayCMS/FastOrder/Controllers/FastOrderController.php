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

    /**
     * Скільки відправок пам'ятати на один токен. Форма на сторінці одна, а
     * кнопка біля кожного товару лише вписує в неї variant_id - тож із одним
     * токеном приходять різні замовлення.
     */
    const MAX_REMEMBERED = 10;

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
        // Без типу Request::post() віддає масив як є, а нижче значення йде
        // ключем масиву: variant_id[]=17 давав фатал уже після запису.
        $variantId = $this->request->post('variant_id', 'integer');

        $order = $ordersHelper->attachUserIfLogin($order, $this->user);

        $errors = $validateExtend->ValidateFastOrder($order,$variantId);

        if (!empty($errors)) {
            return $this->response->setContent(json_encode(['errors' => $errors]), RESPONSE_JSON);
        }

        // Приходить прихованим полем форми. Залишком затиснемо нижче, тут
        // потрібна лише нижня межа - щоб від'ємне не поїхало у відбиток.
        $amount = max(1, (int) $this->request->post('amount', 'integer'));
        $token  = $this->request->post('form_token');

        // До створення: нижче $order підміняється рядком з бази, і склад
        // відправки з нього вже не відновити.
        $submission = self::submissionFingerprint($order, $variantId, $amount);

        // Попередній реліз клав сюди голу адресу замовлення. Про склад тієї
        // відправки вона нічого не каже, тож ігноруємо і пропускаємо далі.
        $created = FormToken::recall(self::FAST_ORDER_FORM, $token);
        $created = is_array($created) ? $created : [];

        $accepted = $this->acceptFastOrder($order, $variantId, $amount);

        if (!$accepted) {
            // Саме ця відправка, а не остання з цим токеном: форма одна на
            // сторінку, тож інакше показали б чуже замовлення.
            if ($submission !== '' && isset($created[$submission])) {
                return $this->answerCreated($created[$submission]);
            }

            // Токен витрачений, але цієї відправки за ним немає: або сторінка
            // повернулась зі старим токеном, або попередню спробу обірвало вже
            // після його зняття. Повтором це не є, тож пропускаємо. Коли токена
            // немає зовсім, рішення вже ухвалив відбиток усередині accept().
            if (!FormToken::isWellFormed($token)) {
                return $this->answerError($frontTranslations, 'okay_cms__fast_order__resend_error');
            }
        }

        // Після гілки повтору: залишок міг впасти до нуля саме цим замовленням,
        // і тоді законний F5 отримував «невірний варіант» замість свого.
        $variant = $variantsEntity->findOne(['id' => $variantId]);
        $stock   = is_object($variant) ? (int) $variant->stock : 0;

        if (!is_object($variant) || ($stock <= 0 && !$this->settings->get('is_preorder'))) {
            // Знімаємо лише те, що зайняв цей запит: інакше стерли б пам'ять
            // про вже створені цим токеном замовлення.
            if ($accepted) {
                FormToken::release(self::FAST_ORDER_FORM, $token);
            }

            return $this->answerError($frontTranslations, 'okay_cms__fast_order__wrong_variant');
        }

        // Cart::addItem() затискав залишком, getPurchases() не затискає нічого.
        $amount = $stock > 0
            ? min($amount, $stock)
            : min($amount, (int) $this->settings->get('max_order_amount'));

        /** @var OrdersEntity $ordersEntity */
        $ordersEntity = $entityFactory->get(OrdersEntity::class);
        $preparedOrder = $ordersHelper->prepareAdd($order);
        $orderId       = $ordersEntity->add($preparedOrder);

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

        // Під відбитком відправки, а не під токеном: повтор має привести на
        // своє замовлення, а не на будь-яке, створене цим токеном раніше.
        if ($submission !== '') {
            $created[$submission] = $orderUrl;

            FormToken::remember(
                self::FAST_ORDER_FORM,
                $token,
                array_slice($created, -self::MAX_REMEMBERED, null, true)
            );
        }

        return $this->answerCreated($orderUrl);
    }

    /**
     * Кількість тут обов'язкова: без неї друге замовлення того самого товару в
     * іншій кількості читалось би як повтор першого.
     */
    private static function submissionFingerprint($order, $variantId, $amount)
    {
        return FormToken::fingerprintOf([$order, $variantId, $amount]);
    }

    private function acceptFastOrder($order, $variantId, $amount)
    {
        return FormToken::accept(
            self::FAST_ORDER_FORM,
            $this->request->post('form_token'),
            [$order, $variantId, $amount]
        );
    }

    private function answerCreated($orderUrl)
    {
        return $this->response->setContent(json_encode([
            'success'           => 1,
            'redirect_location' => Router::generateUrl('order', ['url' => $orderUrl], true),
        ], JSON_UNESCAPED_SLASHES), RESPONSE_JSON);
    }

    private function answerError(FrontTranslations $frontTranslations, $key)
    {
        return $this->response->setContent(json_encode([
            'errors' => [$frontTranslations->getTranslation($key)],
        ]), RESPONSE_JSON);
    }
}
