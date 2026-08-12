<?php

namespace Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Дефекти, знайдені ревʼю вже після того, як механізм одноразових токенів
 * поїхав у роботу. Спільне в них одне: жоден не падає й не пише в лог — усі
 * тихо віддають покупцеві не той результат, якого він чекав.
 */
class FormTokenFollowupsTest extends TestCase
{
    private const FAST_ORDER = 'Okay/Modules/OkayCMS/FastOrder/Controllers/FastOrderController.php';
    private const CART       = 'Okay/Controllers/CartController.php';

    /** addItem() затискав кількість залишком, getPurchases() не затискає нічого. */
    public function testFastOrderClampsTheAmountByStock()
    {
        $source = $this->read(self::FAST_ORDER);

        $this->assertStringContainsString('min($amount, $stock)', $source);
        $this->assertStringContainsString("get('max_order_amount')", $source);
    }

    /** Товар не в наявності не замовляють — це теж робив addItem(). */
    public function testFastOrderRefusesAnUnavailableVariant()
    {
        $source = $this->read(self::FAST_ORDER);

        $this->assertStringContainsString("\$stock <= 0 && !\$this->settings->get('is_preorder')", $source);
    }

    /** Інакше в базі лишається замовлення без позицій і без суми. */
    public function testFastOrderChecksAvailabilityBeforeCreatingTheOrder()
    {
        $source = $this->read(self::FAST_ORDER);

        $checkAt = strpos($source, '$stock <= 0');
        $writeAt = strpos($source, '$ordersEntity->add(');

        $this->assertNotFalse($checkAt, 'перевірку наявності прибрано');
        $this->assertNotFalse($writeAt, 'не знайдено запису замовлення - тест застарів');
        $this->assertLessThan($writeAt, $checkAt, 'замовлення пишеться до перевірки наявності');
    }

    /**
     * ...але після гілки повтору: залишок міг впасти до нуля саме цим
     * замовленням, і тоді F5 давав «невірний варіант» замість свого замовлення.
     */
    public function testFastOrderAnswersARepeatBeforeItChecksAvailability()
    {
        $source = $this->read(self::FAST_ORDER);

        $repeatAt = strpos($source, '$this->acceptFastOrder(');
        $checkAt  = strpos($source, '$stock <= 0');

        $this->assertNotFalse($repeatAt, 'гілку повтору прибрано');
        $this->assertLessThan($checkAt, $repeatAt, 'наявність перевіряється до гілки повтору');
    }

    /** Без типу значення йде ключем масиву: variant_id[]=17 давав фатал. */
    public function testFastOrderReadsVariantIdAsInteger()
    {
        $source = $this->read(self::FAST_ORDER);

        $this->assertStringContainsString("post('variant_id', 'integer')", $source);
        $this->assertStringNotContainsString("post('variant_id')", $source);
    }

    /**
     * Форма на сторінці одна, кнопка біля товару лише вписує в неї variant_id -
     * тож із одним токеном приходять різні замовлення.
     */
    public function testFastOrderRemembersEverySubmissionSeparately()
    {
        $source = $this->read(self::FAST_ORDER);

        $this->assertStringContainsString('$created[$submission]', $source);
        $this->assertStringContainsString('array_slice($created', $source);
        $this->assertStringContainsString('MAX_REMEMBERED', $source);
    }

    /**
     * consumeFingerprint() тримає одну комірку на форму й перезаписує її: другий
     * товар затирав відбиток першого. Запасний шлях лишається всередині accept().
     */
    public function testFastOrderDoesNotHandRollTheFingerprintPath()
    {
        $source = $this->read(self::FAST_ORDER);

        $this->assertStringNotContainsString('FormToken::consumeFingerprint(', $source);
        $this->assertStringContainsString('FormToken::accept(', $source);
    }

    /** Без кількості те саме в іншій кількості читалось як повтор першого. */
    public function testFastOrderFingerprintCoversTheAmount()
    {
        $source = $this->read(self::FAST_ORDER);

        $this->assertMatchesRegularExpression(
            '/fingerprintOf\(\[\$order, \$variantId, \$amount\]\)/',
            $source
        );
        $this->assertMatchesRegularExpression(
            '/accept\(\s*self::FAST_ORDER_FORM,\s*.+,\s*\[\$order, \$variantId, \$amount\]/s',
            $source
        );
    }

    /** fingerprintOf() віддає '' коли json_encode() падає - це не збіг. */
    public function testFastOrderIgnoresAnEmptyFingerprint()
    {
        $source = $this->read(self::FAST_ORDER);

        $this->assertSame(
            2,
            substr_count($source, "\$submission !== ''"),
            'порожній відбиток має відсіюватись і на пошуку, і на записі'
        );
    }

    /**
     * Знімати можна лише те, що зайняв цей запит: інакше відмова за наявністю
     * стирала б пам'ять про вже створені цим токеном замовлення.
     */
    public function testFastOrderReleasesOnlyWhatItTook()
    {
        $source = $this->read(self::FAST_ORDER);

        $this->assertMatchesRegularExpression(
            '/if \(\$accepted\) \{\s*FormToken::release\(self::FAST_ORDER_FORM/s',
            $source
        );
    }

    /** Покупець міг прибрати останню позицію в сусідній вкладці. */
    public function testEmptyCartIsNotTreatedAsADuplicateByItself()
    {
        $branch = $this->emptyCartBranch();

        $this->assertStringContainsString('FormToken::recall(', $branch);
        $this->assertStringContainsString('$this->answerEmptyCart()', $branch);
    }

    /**
     * ...а тема без form_token такого доказу дати не може: для неї лишається
     * колишня поведінка, інакше «назад» показує помилку замість замовлення.
     */
    public function testTokenlessThemesKeepTheOldEmptyCartBehaviour()
    {
        $branch = $this->emptyCartBranch();

        $this->assertStringContainsString('!FormToken::isWellFormed($token)', $branch);
        $this->assertStringContainsString('$this->answerDuplicateCheckout()', $branch);
    }

    /** HTML у відповідь обробник auto_submit не розбирає й шле форму ще раз. */
    public function testEmptyCartAnswersAjaxWithJson()
    {
        $source = $this->read(self::CART);

        $at = strpos($source, 'private function answerEmptyCart()');
        $this->assertNotFalse($at, 'відповідь на порожній кошик прибрано');

        $method = substr($source, $at, 700);

        $this->assertStringContainsString("post('ajax')", $method);
        $this->assertStringContainsString('RESPONSE_JSON', $method);
    }

    /**
     * Помилка ставиться лише при порожньому кошику, тож усередині форми
     * оформлення повідомлення не рендерилось би ніколи.
     */
    #[DataProvider('themeProvider')]
    public function testCartEmptyMessageLivesOutsideTheCheckoutForm($theme)
    {
        $source = $this->read("design/{$theme}/html/cart.tpl");

        $messageAt = strpos($source, 'cart_empty_error');
        $buttonAt  = strpos($source, 'name="checkout"');

        $this->assertNotFalse($messageAt, "{$theme}: повідомлення cart_empty_error відсутнє");
        $this->assertNotFalse($buttonAt, "{$theme}: не знайдено кнопки оформлення - тест застарів");
        $this->assertGreaterThan(
            $buttonAt,
            $messageAt,
            "{$theme}: повідомлення стоїть усередині форми оформлення, тобто не рендериться ніколи"
        );
    }

    public static function themeProvider()
    {
        return [
            'okay_shop' => ['okay_shop'],
            'vibe_shop' => ['vibe_shop'],
        ];
    }

    /** Якщо запис не пройшов, повтором відправка не є. */
    #[DataProvider('releaseSiteProvider')]
    public function testFailedWriteReleasesTheToken($file, $form)
    {
        $source = $this->read($file);

        $this->assertStringContainsString("FormToken::release({$form}", $source, $file);
    }

    public static function releaseSiteProvider()
    {
        return [
            'callback' => ['Okay/Helpers/CommonHelper.php', 'self::CALLBACK_FORM'],
            'feedback' => ['Okay/Controllers/FeedbackController.php', 'self::FEEDBACK_FORM'],
            'comment'  => ['Okay/Helpers/CommentsHelper.php', 'self::COMMENT_FORM'],
        ];
    }

    private function emptyCartBranch()
    {
        $source = $this->read(self::CART);

        $at = strpos($source, '$cart->isEmpty');
        $this->assertNotFalse($at, 'гілку порожнього кошика прибрано');

        return substr($source, $at, 2400);
    }

    private function read($file)
    {
        $path = dirname(__DIR__, 2) . '/' . $file;

        $this->assertFileExists($path);

        return file_get_contents($path);
    }
}
