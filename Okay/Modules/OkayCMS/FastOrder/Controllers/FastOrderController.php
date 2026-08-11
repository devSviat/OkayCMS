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
     * Скільки відправок пам'ятати на один токен.
     *
     * Форма швидкого замовлення на сторінці одна: fast_order_form.tpl рендерить
     * її прихованою, а кнопка біля кожного товару лише вписує туди variant_id.
     * Тобто токен теж один на весь рендер, а відправок із нього буває кілька, і
     * кожна - своє замовлення. Одна комірка пам'яті на форму їх плутає, тому
     * пам'ятаємо кожну відправку окремо.
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
        // Тип обов'язковий: Request::post() без нього віддає масив як є, а
        // нижче значення йде ключем масиву - variant_id[]=17 давав фатал уже
        // після того, як рядок замовлення записано.
        $variantId = $this->request->post('variant_id', 'integer');

        $order = $ordersHelper->attachUserIfLogin($order, $this->user);

        $errors = $validateExtend->ValidateFastOrder($order,$variantId);

        if (!empty($errors)) {
            return $this->response->setContent(json_encode(['errors' => $errors]), RESPONSE_JSON);
        }

        // Кількість приходить прихованим полем форми, тобто повністю з клієнта.
        // Затискаємо її нижче, залишком; тут потрібна лише нижня межа, щоб
        // від'ємне значення не поїхало у відбиток відправки.
        $amount = max(1, (int) $this->request->post('amount', 'integer'));
        $token  = $this->request->post('form_token');

        // Рахуємо до створення замовлення: нижче $order підміняється рядком з
        // бази, і склад відправки з нього вже не відновити.
        $submission = self::submissionFingerprint($order, $variantId, $amount);

        $created = FormToken::recall(self::FAST_ORDER_FORM, $token);
        // Попередній реліз клав сюди голу адресу замовлення. Такий запис нічого
        // не каже про склад тієї відправки, тож покластись на нього не можна:
        // ігноруємо його і пропускаємо відправку далі. Зайве замовлення видно
        // й виправно, втрачене - ні.
        $created = is_array($created) ? $created : [];

        $accepted = $this->acceptFastOrder($order, $variantId, $amount);

        if (!$accepted) {
            // Це замовлення вже створене цим токеном - веземо покупця саме на
            // нього. Пошук іде по ЦІЙ відправці, а не по останній: форма одна
            // на сторінку, тож із тим самим токеном приходять і зовсім інші
            // замовлення, і сплутати їх означало б показати чуже.
            if ($submission !== '' && isset($created[$submission])) {
                return $this->answerCreated($created[$submission]);
            }

            // Токен витрачений, але саме цієї відправки за ним немає. Отже це
            // не повтор, а нова відправка зі сторінки, яка повернулась разом
            // зі старим токеном, - або попередню спробу обірвало вже після
            // зняття токена. Глухий кут із порадою «спробуйте ще раз» тут
            // нічим би не скінчився, тож пропускаємо.
            //
            // Коли токена немає зовсім, рішення вже ухвалив відбиток усередині
            // accept() - і тоді це справді повтор.
            if (!FormToken::isWellFormed($token)) {
                return $this->answerError($frontTranslations, 'okay_cms__fast_order__resend_error');
            }
        }

        // Наявність перевіряємо ПІСЛЯ гілки повтору: замовлення вже створене, а
        // залишок міг впасти до нуля саме ним. Інакше законний F5 отримав би
        // «невірний варіант» замість власного замовлення.
        $variant = $variantsEntity->findOne(['id' => $variantId]);
        $stock   = is_object($variant) ? (int) $variant->stock : 0;

        if (!is_object($variant) || ($stock <= 0 && !$this->settings->get('is_preorder'))) {
            // Відправку не прийнято, тож і повтором вона не є. Без release()
            // токен лишався б витраченим, і друга спроба пішла б гілкою «нова
            // відправка зі старим токеном». Знімаємо лише те, що зайняв цей
            // запит: чужої пам'яті тут не буває - токен із записаними
            // відправками повторно accept() не проходить.
            if ($accepted) {
                FormToken::release(self::FAST_ORDER_FORM, $token);
            }

            return $this->answerError($frontTranslations, 'okay_cms__fast_order__wrong_variant');
        }

        // Cart::addItem() затискав кількість залишком; getPurchases() не
        // затискає нічого, тож затиск потрібен тут - і до створення замовлення.
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

        // Адресу пам'ятаємо під відбитком самої відправки, а не під токеном:
        // повтор має привести на СВОЄ замовлення, а не на будь-яке, створене
        // цим токеном раніше.
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
     * Відбиток відправки: те саме замовлення того самого варіанта в тій самій
     * кількості. Кількість тут обов'язкова - без неї друге замовлення того
     * самого товару в іншій кількості читалось би як повтор першого.
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
