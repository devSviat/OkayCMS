<?php


namespace Okay\Controllers;


use Okay\Helpers\CartHelper;
use Okay\Helpers\CouponHelper;
use Okay\Helpers\MetadataHelpers\CartMetadataHelper;
use Okay\Requests\CartRequest;
use Okay\Core\Notify;
use Okay\Core\Router;
use Okay\Entities\DeliveriesEntity;
use Okay\Entities\CurrenciesEntity;
use Okay\Entities\CouponsEntity;
use Okay\Entities\OrdersEntity;
use Okay\Core\Request;
use Okay\Core\Cart;
use Okay\Core\Languages;
use Okay\Core\Security\FormToken;
use Okay\Helpers\DeliveriesHelper;
use Okay\Helpers\PaymentsHelper;
use Okay\Helpers\ValidateHelper;
use Okay\Helpers\OrdersHelper;

class CartController extends AbstractController
{
    /** Форма оформлення для FormToken. */
    const CHECKOUT_FORM = 'checkout';

    /**
     * Відбиток тут враховує ще й склад кошика: те саме ім'я з тим самим
     * телефоном, але з іншим набором товарів - це нове замовлення.
     */
    private function acceptCheckout($order)
    {
        $cartItems = isset($_SESSION['shopping_cart']) && is_array($_SESSION['shopping_cart'])
            ? $_SESSION['shopping_cart']
            : [];

        return FormToken::accept(
            self::CHECKOUT_FORM,
            $this->request->post('form_token'),
            [$order, $cartItems]
        );
    }

    /**
     * Куди вести відсіяний повтор. Спершу - замовлення, створене саме цим
     * токеном; воно точне. Якщо тема токена не шле, лишається останнє
     * замовлення сесії, а якщо немає й того - сторінка кошика.
     */
    private function lastOrderUrl()
    {
        $url = FormToken::recall(self::CHECKOUT_FORM, $this->request->post('form_token'))
            ?? $_SESSION[OrdersHelper::LAST_ORDER_URL]
            ?? null;

        if (is_string($url) && $url !== '') {
            return Router::generateUrl('order', ['url' => $url], true);
        }

        return Router::generateUrl('cart', [], true);
    }

    /**
     * Форма кошика вміє слати ajax=1 і чекає JSON; гілка успіху це враховує,
     * тож і відмова має відповідати тим самим форматом - інакше обробник
     * отримує HTML і пересилає всю форму ще раз.
     */
    private function answerDuplicateCheckout()
    {
        $url = $this->lastOrderUrl();

        if ($this->request->post('ajax')) {
            $this->response->setContent(
                json_encode(['auto_submit' => false, 'url' => $url], JSON_UNESCAPED_SLASHES),
                RESPONSE_JSON
            );
            $this->response->sendContent();
            exit;
        }

        $this->response->redirectTo($url, 303);
    }

    /*Отображение заказа*/
    public function render(
        DeliveriesEntity   $deliveriesEntity,
        OrdersEntity       $ordersEntity,
        CouponsEntity      $couponsEntity,
        CurrenciesEntity   $currenciesEntity,
        Languages          $languages,
        Request            $request,
        Notify             $notify,
        Cart               $cart,
        DeliveriesHelper   $deliveriesHelper,
        PaymentsHelper     $paymentsHelper,
        OrdersHelper       $ordersHelper,
        CartRequest        $cartRequest,
        CartHelper         $cartHelper,
        ValidateHelper     $validateHelper,
        CouponHelper       $couponHelper,
        CartMetadataHelper $cartMetadataHelper
    ) {

        // Кожен POST на /cart щось змінює: додає варіант, оновлює кількості,
        // застосовує купон, оформлює замовлення. Тому охорона одна і на вході,
        // а не по гілці - інакше наступна додана гілка знову лишиться голою.
        //
        // GET сюди приходить за самою сторінкою кошика, тож умова обов'язкова:
        // requireCustomerCsrf() віддає 405 на будь-що, крім POST.
        if ($request->method('post')) {
            $this->requireCustomerCsrf();
        }

        // Додавання в кошик без JS: сюди постить форма fn_variants з картки та
        // сторінки товару.
        //
        // Раніше гілка читала $_GET, і сторонній <img src="/cart?variant=17">
        // наповнював кошик відвідувача. Решту мутацій кошика форк закрив, а цю
        // ні - вона єдина, що жила у власному методі, а не в окремому маршруті.
        //
        // 303, а не 301: 301 на POST кешується назавжди, тож повторне додавання
        // того самого варіанта більше не доходило б до сервера.
        if ($variantId = $request->post('variant', 'integer')) {
            $cart->addItem($variantId, $request->post('amount', 'integer'));
            $this->response->redirectTo(Router::generateUrl('cart', [], true), 303);
        }

        // Если нам запостили amounts, обновляем их
        if ($amounts = $request->post('amounts')) {
            foreach ($amounts as $variantId => $amount) {
                $cart->updateItem($variantId, $amount);
            }
        }
        
        $this->setMetadataHelper($cartMetadataHelper);
        
        $cart = $cart->get();
        /*Оформление заказа*/
        if (isset($_POST['checkout'])) {
            $order = $cartRequest->postOrder();
            $order = $ordersHelper->attachUserIfLogin($order, $this->user);

            if ($error = $validateHelper->getCartValidateError($order)) {
                $this->design->assign('error', $error);
            } elseif ($cart->isEmpty) {
                // Замовлення з порожнього кошика не буває. Сюди приходить друга
                // вкладка кошика: перша вже оформила замовлення й очистила
                // кошик, а її токен друга не бачила, тож за токеном це нова
                // відправка. Перевірка складу кошика - те, чого токен знати не
                // може, і саме вона тримає цей випадок.
                $this->answerDuplicateCheckout();
            } elseif (!$this->acceptCheckout($order)) {
                // Не помилка покупця: замовлення вже створене, тож друга
                // вкладка має показати те саме, що й перша.
                $this->answerDuplicateCheckout();
            } else {
                // Добавляем заказ в базу
                $order->lang_id = $languages->getLangId();
                $preparedOrder  = $ordersHelper->prepareAdd($order);
                $orderId        = $ordersHelper->add($preparedOrder);

                if (isset($_SESSION['coupon_code'])){
                    $couponHelper->registerUseIfExists($_SESSION['coupon_code']);
                }

                $preparedCart = $cartHelper->prepareCart($cart, $orderId);
                $preparedCart = $cartHelper->cartToOrder($preparedCart, $orderId);
                $preparedCart = $cartHelper->prepareDiscounts($preparedCart, $orderId);
                $cartHelper->discountsToDB($preparedCart);

                $order = $ordersEntity->get((int) $orderId);
                if (!empty($order->delivery_id)) {
                    $delivery          = $deliveriesEntity->get((int) $order->delivery_id);
                    $deliveryPriceInfo = $deliveriesHelper->prepareDeliveryPriceInfo($delivery, $order);
                    $deliveriesHelper->updateDeliveryPriceInfo($deliveryPriceInfo, $order);
                }

                $ordersEntity->updateTotalPrice($order->id);
                $ordersHelper->finalCreateOrderProcedure($order);

                // Прив'язуємо замовлення до витраченого токена: повтор саме з
                // цієї сторінки має привести на нього, а не на те, що сесія
                // оформила останнім.
                FormToken::remember(
                    self::CHECKOUT_FORM,
                    $this->request->post('form_token'),
                    $order->url
                );


                // Отправляем письмо пользователю
                $notify->emailOrderUser($order->id);

                // Отправляем письмо администратору
                $notify->emailOrderAdmin($order->id);

                $cart->clear();

                // Перенаправляем на страницу заказа или отправляем форму для автосабмита или урл заказа
                if ($this->request->post('ajax')) {
                    $content = $cartHelper->getAjaxOrderContent($order);
                    return $this->response->setContent(json_encode($content, JSON_UNESCAPED_SLASHES), RESPONSE_JSON);
                } else {
                    $this->response->redirectTo(Router::generateUrl('order', ['url' => $order->url], true));
                }
            }
        } else {
            
            if ($request->post('amounts')) {
                $couponCode = $cartRequest->postCoupon();
                if (empty($couponCode)) {
                    $cart->applyCoupon('');
                    $this->response->redirectTo(Router::generateUrl('cart', [], true));
                } else {
                    $coupon = $couponsEntity->get((string)$couponCode);
                    if (empty($coupon) || !$coupon->valid) {
                        $cart->applyCoupon($couponCode);
                        $this->design->assign('coupon_error', 'invalid');
                    } else {
                        $cart->applyCoupon($couponCode);
                        $this->response->redirectTo(Router::generateUrl('cart', [], true));
                    }
                }
            }

            // Данные пользователя по умолчанию
            $this->design->assign('request_data', $cartHelper->getDefaultCartData($this->user));
        }

        // Способы доставки и оплаты
        $paymentMethods = $paymentsHelper->getCartPaymentsList($cart);
        $deliveries     = $deliveriesHelper->getCartDeliveriesList($cart, $paymentMethods);
        $activeDelivery = $deliveriesHelper->getActiveDeliveryMethod($deliveries, $this->user);
        $activePayment  = $paymentsHelper->getActivePaymentMethod($paymentMethods, $activeDelivery, $this->user);

        $this->design->assign('all_currencies', $currenciesEntity->mappedBy('id')->find());
        $this->design->assign('deliveries', $deliveries);
        $this->design->assign('payment_methods', $paymentMethods);
        $this->design->assign('active_delivery', $activeDelivery);
        $this->design->assign('active_payment', $activePayment);

        if ($couponsEntity->count(['valid'=>1])>0) {
            $this->design->assign('coupon_request', true);
        }

        $this->design->assign('noindex_follow', true);
        
        $this->response->setContent('cart.tpl');
    }
    
    public function cartAjax(
        CouponsEntity    $couponsEntity,
        CurrenciesEntity $currenciesEntity,
        Request          $request,
        Cart             $cart,
        DeliveriesHelper $deliveriesHelper,
        PaymentsHelper   $paymentsHelper,
        CartHelper       $cartHelper
    ) {
        $this->requireCustomerCsrf();

        $action     = $request->post('action');
        $variantId  = $request->post('variant_id', 'integer');
        $amount     = $request->post('amount', 'integer');
        
        switch ($action) {
            case 'update_citem':
                $cart->updateItem($variantId, $amount);
                break;
            case 'remove_citem':
                $cart->deleteItem($variantId);
                break;
            case 'add_citem':
                $cart->addItem($variantId, $amount);
                break;
            default:
                break;
        }

        $cart = $cart->get();
        $this->design->assign('cart', $cart);

        $this->design->assign('all_currencies', $currenciesEntity->mappedBy('id')->find());

        /*Рабтаем с товарами в корзине*/
        if ($cart->isEmpty === false) {
            // okay.js posts the coupon (ajax_coupon(): type "post"), and so does
            // every other action this method handles - $action above is read from
            // $_POST. This branch alone looked in $_GET, so it never ran and no
            // coupon could ever be applied from the cart. GET is still accepted so
            // that a coupon carried in a URL keeps working.
            $couponCodePosted = isset($_POST['coupon_code']);
            if ($couponCodePosted || isset($_GET['coupon_code'])) {
                $couponCode = trim($couponCodePosted
                    ? $request->post('coupon_code', 'string')
                    : $request->get('coupon_code', 'string'));
                if (empty($couponCode)) {
                    $cart->applyCoupon('');
                    if ($action == 'coupon_apply') {
                        $this->design->assign('coupon_error', 'empty');
                    }
                } else {
                    $coupon = $couponsEntity->get((string)$couponCode);
                    if (empty($coupon) || !$coupon->valid) {
                        $cart->applyCoupon($couponCode);
                        $this->design->assign('coupon_error', 'invalid');
                    } else {
                        $cart->applyCoupon($couponCode);
                    }
                }
            }

            if ($couponsEntity->count(['valid'=>1])>0) {
                $this->design->assign('coupon_request', true);
            }

            $cart = $cart->get();
        }

        $paymentMethods = $paymentsHelper->getCartPaymentsList($cart);
        $deliveries = $deliveriesHelper->getCartDeliveriesList($cart, $paymentMethods);
        
        $result = $cartHelper->getAjaxCartResult($cart, $this->currency, $paymentMethods, $deliveries, $action, $variantId, $amount);
        
        $this->response->setContent(json_encode($result), RESPONSE_JSON);
    }

    public function removeItem(Cart $cart, $variantId)
    {
        $this->requireCustomerCsrf();

        $cart->deleteItem($variantId);
        $this->response->redirectTo(Router::generateUrl('cart', [], true));
    }

    public function addItem(Cart $cart, $variantId)
    {
        $this->requireCustomerCsrf();

        $cart->addItem($variantId);
        $this->response->redirectTo(Router::generateUrl('cart', [], true));
    }
}