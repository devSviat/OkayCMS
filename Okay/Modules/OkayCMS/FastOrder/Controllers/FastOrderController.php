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
        // Тип обов'язковий: Request::post() без нього віддає масив як є, а
        // нижче значення йде ключем масиву - variant_id[]=17 давав фатал уже
        // після того, як рядок замовлення записано.
        $variantId = $this->request->post('variant_id', 'integer');

        $order = $ordersHelper->attachUserIfLogin($order, $this->user);

        $errors = $validateExtend->ValidateFastOrder($order,$variantId);

        if (!empty($errors)) {
            return $this->response->setContent(json_encode(['errors' => $errors]), RESPONSE_JSON);
        }

        $variant = $variantsEntity->findOne(['id' => $variantId]);

        // Кількість приходить прихованим полем форми, тобто повністю з клієнта.
        // Cart::addItem() її затискав залишком; getPurchases() не затискає
        // нічого, тож затиск потрібен тут - і до створення замовлення.
        $amount = max(1, (int) $this->request->post('amount', 'integer'));
        $stock  = is_object($variant) ? (int) $variant->stock : 0;

        if (!is_object($variant) || ($stock <= 0 && !$this->settings->get('is_preorder'))) {
            return $this->response->setContent(json_encode([
                'errors' => [$frontTranslations->getTranslation('okay_cms__fast_order__wrong_variant')],
            ]), RESPONSE_JSON);
        }

        $amount = $stock > 0
            ? min($amount, $stock)
            : min($amount, (int) $this->settings->get('max_order_amount'));

        $token = $this->request->post('form_token');

        // Рахуємо до створення замовлення: нижче $order підміняється рядком з
        // бази, і склад відправки з нього вже не відновити.
        $submission = self::submissionFingerprint($order, $variantId);

        if (!$this->acceptFastOrder($order, $variantId)) {
            $created = FormToken::recall(self::FAST_ORDER_FORM, $token);

            // Замовлення вже створене цим токеном - але тільки якщо це та сама
            // відправка. Форма стоїть на сторінці списку й повертається з
            // bfcache разом із уже витраченим токеном, тож наступне замовлення
            // ІНШОГО товару приходило з тим самим токеном і мовчки зникало,
            // віддавши покупцеві сторінку попереднього замовлення.
            if (is_array($created) && isset($created['url'], $created['fingerprint'])) {
                if (hash_equals($created['fingerprint'], $submission)) {
                    return $this->response->setContent(json_encode([
                        'success'           => 1,
                        'redirect_location' => Router::generateUrl('order', ['url' => $created['url']], true),
                    ], JSON_UNESCAPED_SLASHES), RESPONSE_JSON);
                }

                // Той самий токен, але інша відправка: це не повтор. Рішення
                // віддаємо відбитку - він відсіє справжній дубль і пропустить
                // нове замовлення.
                if (FormToken::consumeFingerprint(self::FAST_ORDER_FORM, $submission)) {
                    $created = null;
                }
            }

            if ($created !== null) {
                return $this->response->setContent(json_encode([
                    'success'           => 1,
                    'redirect_location' => Router::generateUrl(
                        'order',
                        ['url' => is_array($created) ? $created['url'] : $created],
                        true
                    ),
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

        // Разом з адресою памʼятаємо відбиток самої відправки: повтор із тим
        // самим токеном має привести на СВОЄ замовлення, а не на будь-яке,
        // створене цим токеном раніше.
        FormToken::remember(self::FAST_ORDER_FORM, $token, [
            'url'         => $orderUrl,
            'fingerprint' => $submission,
        ]);

        return $this->response->setContent(json_encode([
            'success'           => 1,
            'redirect_location' => Router::generateUrl('order', ['url' => $orderUrl], true)
        ]), RESPONSE_JSON);
    }

    /**
     * Відбиток відправки: те саме замовлення того самого варіанта. Служить
     * розрізненню "це повтор" і "це нова відправка зі старим токеном".
     */
    private static function submissionFingerprint($order, $variantId)
    {
        return FormToken::fingerprintOf([$order, $variantId]);
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
