<?php

namespace Modules\OkayCMS\RozetkaPay;

use PHPUnit\Framework\TestCase;

/**
 * Повернення грошей виконувалось за GET зі звичайного посилання, а
 * checkSession() гардить лише небезпечні методи — тобто токен для GET не
 * вимагався ніколи і повернення робив будь-який сторонній запит у браузері
 * менеджера.
 */
class RefundCsrfTest extends TestCase
{
    private function source($relativePath)
    {
        $path = dirname(__DIR__, 4) . '/' . $relativePath;
        $this->assertFileExists($path);

        return file_get_contents($path);
    }

    private function controller()
    {
        return $this->source('Okay/Modules/OkayCMS/RozetkaPay/Backend/Controllers/RefundAdmin.php');
    }

    private function template()
    {
        return $this->source('Okay/Modules/OkayCMS/RozetkaPay/Backend/design/html/refund.tpl');
    }

    /**
     * Розмітка без блоку <script>. Інакше перевірки ловлять власні коментарі
     * шаблона — той, що пояснює, чому кнопка не сабміт, називає сабміт.
     */
    private function markup()
    {
        return preg_replace('#<script\b.*?</script>#s', '', $this->template());
    }

    private function script()
    {
        $this->assertSame(1, preg_match('#<script\b.*?</script>#s', $this->template(), $matches));

        return $matches[0];
    }

    public function testOrderIdComesFromPost()
    {
        $this->assertStringContainsString("\$this->request->post('order', 'integer')", $this->controller());
    }

    /**
     * Не про стиль: доки id читається з рядка запиту, дію можна виконати
     * навігацією, а навігація токена не несе.
     */
    public function testControllerReadsNothingFromTheQueryString()
    {
        $source = $this->controller();

        $this->assertStringNotContainsString('$_GET', $source);
        $this->assertStringNotContainsString('$this->request->get(', $source);
    }

    public function testTemplateHasNoLinkThatTriggersTheRefund()
    {
        $markup = $this->markup();

        $this->assertStringNotContainsString('<a ', $markup);
        $this->assertStringNotContainsString('href', $markup);
    }

    /**
     * Саме значення, а не саму лише назву поля: із порожнім чи вигаданим
     * токеном гард віддає 403 на кожне повернення, а перевірка на підрядок
     * `session_id` цього не бачить.
     */
    public function testRequestCarriesTheCsrfToken()
    {
        $this->assertStringContainsString(
            'data-session="{$smarty.session.id|escape}"',
            $this->markup()
        );
        $this->assertStringContainsString("addField('session_id', button.getAttribute('data-session'))", $this->script());
    }

    public function testRequestIsSentByPost()
    {
        $this->assertStringContainsString("form.method = 'post'", $this->script());
    }

    /**
     * Блок вставляється всередину форми замовлення (`order.tpl`), а вкладену
     * форму HTML-парсер викидає — на цьому вже горів безджаваскриптовий кошик
     * okay_shop. Тому форма будується в JS і кладеться поза нею.
     */
    public function testTemplateDeclaresNoFormOfItsOwn()
    {
        $this->assertStringNotContainsString('<form', $this->markup());
    }

    /**
     * Кнопка-сабміт стала б першою в формі замовлення, тобто кнопкою за
     * замовчуванням: Enter у будь-якому полі замовлення повертав би гроші.
     */
    public function testRefundButtonIsNotASubmit()
    {
        $this->assertSame(1, preg_match('#<button\b[^>]*>#', $this->markup(), $matches));

        $this->assertStringContainsString('type="button"', $matches[0]);
        $this->assertStringNotContainsString('type="submit"', $matches[0]);
    }

    /**
     * `Refund::refund()` читає ключі з методу оплати самого замовлення, тож без
     * цього гарда чужий id ішов у розетку з чужими реквізитами. Перевіряємо
     * саме порядок: наявність умови нижче за виклик шлюзу нічого не дала б.
     */
    public function testPaymentMethodIsCheckedBeforeTheGatewayIsCalled()
    {
        $source = $this->controller();

        $guard = strpos($source, "\$paymentMethod->module !== 'OkayCMS/RozetkaPay'");
        $call = strpos($source, '$refund->refund(');

        $this->assertNotFalse($guard, 'немає звірки модуля платіжного методу');
        $this->assertNotFalse($call);
        $this->assertLessThan($call, $guard, 'звірка модуля стоїть після виклику шлюзу');
    }

    /**
     * Гард мусить обривати виконання, а не просто щось залогувати.
     */
    public function testWrongPaymentMethodStopsExecution()
    {
        $source = $this->controller();

        $guard = strpos($source, "\$paymentMethod->module !== 'OkayCMS/RozetkaPay'");
        $call = strpos($source, '$refund->refund(');

        // Без цього за відсутнього гарда strpos дає false, substr бере його за
        // нуль, і перевірка проходить на return; із зовсім іншого місця.
        $this->assertNotFalse($guard, 'немає звірки модуля платіжного методу');
        $this->assertNotFalse($call);

        $this->assertStringContainsString('return;', substr($source, $guard, $call - $guard));
    }

    /**
     * Шаблон і контролер мусять звірятися за одним полем. Поки шаблон дивився
     * на `name`, кнопка зникала на будь-якій назві методу, крім дослівної.
     */
    public function testButtonIsGatedOnTheSameFieldAsTheController()
    {
        $this->assertStringContainsString(
            "\$payment_method->module === 'OkayCMS/RozetkaPay'",
            $this->markup()
        );
        $this->assertStringNotContainsString('$payment_method->name', $this->markup());
    }

    /**
     * Точка вставки справді лежить усередині форми замовлення — саме цим
     * продиктовані два тести вище. Якщо ядро її перенесе, це має впасти й
     * змусити перечитати міркування, а не тихо лишити мертві обмеження.
     */
    public function testInsertionPointIsStillInsideTheOrderForm()
    {
        $orderTemplate = $this->source('backend/design/html/order.tpl');

        $anchor = strpos($orderTemplate, '{*Метки заказа*}');
        $formOpen = strpos($orderTemplate, '<form ');
        $formClose = strpos($orderTemplate, '</form>');

        $this->assertNotFalse($anchor, 'у order.tpl немає якоря, за яким вставляється refund.tpl');
        $this->assertNotFalse($formOpen);
        $this->assertNotFalse($formClose);
        $this->assertGreaterThan($formOpen, $anchor);
        $this->assertLessThan($formClose, $anchor);
    }
}
