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

    /**
     * Кількість приходить прихованим полем форми. Cart::addItem() її затискав
     * залишком; getPurchases(), яким його замінили, не затискає нічого.
     */
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

    /**
     * Відмова має статись до запису: інакше в базі лишається замовлення без
     * позицій і без суми.
     */
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
     * Request::post() без типу віддає масив як є, а значення йде ключем
     * масиву — variant_id[]=17 давав фатал уже після запису замовлення.
     */
    public function testFastOrderReadsVariantIdAsInteger()
    {
        $source = $this->read(self::FAST_ORDER);

        $this->assertStringContainsString("post('variant_id', 'integer')", $source);
        $this->assertStringNotContainsString("post('variant_id')", $source);
    }

    /**
     * Форма швидкого замовлення стоїть на сторінці списку й повертається з
     * bfcache разом із витраченим токеном. Без звірки відбитка наступне
     * замовлення іншого товару мовчки зникало, віддавши сторінку попереднього.
     */
    public function testFastOrderTellsARepeatFromANewSubmissionWithAStaleToken()
    {
        $source = $this->read(self::FAST_ORDER);

        $this->assertStringContainsString("'fingerprint' =>", $source);
        $this->assertStringContainsString('hash_equals(', $source);
        $this->assertStringContainsString('FormToken::consumeFingerprint(', $source);
    }

    /**
     * Порожній кошик сам по собі не означає повтору: покупець міг прибрати
     * останню позицію в сусідній вкладці. Редирект на останнє замовлення сесії
     * показував би підтвердження замовлення, якого він не робив.
     */
    public function testEmptyCartIsNotTreatedAsADuplicateByItself()
    {
        $source = $this->read('Okay/Controllers/CartController.php');

        $emptyAt = strpos($source, '$cart->isEmpty');
        $this->assertNotFalse($emptyAt, 'гілку порожнього кошика прибрано');

        $branch = substr($source, $emptyAt, 1200);

        $this->assertStringContainsString('FormToken::recall(', $branch);
        $this->assertStringContainsString("'cart_empty'", $branch);
    }

    /**
     * Відбиток знімається до запису. Якщо запис не пройшов, повтором відправка
     * не є — інакше друга спроба піде гілкою дубля й покаже «прийнято», не
     * створивши рядка.
     */
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

    private function read($file)
    {
        $path = dirname(__DIR__, 2) . '/' . $file;

        $this->assertFileExists($path);

        return file_get_contents($path);
    }
}
