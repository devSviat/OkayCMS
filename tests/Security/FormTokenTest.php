<?php

namespace Security;

use Okay\Core\Security\FormToken;
use PHPUnit\Framework\TestCase;

/**
 * CSRF-токен вітрини захищає від підробки запиту, але не від повтору того
 * самого: подвійний клік по «Замовити дзвінок» створював дві заявки й два
 * листи, F5 на сторінці зворотного зв'язку - другий відгук.
 */
class FormTokenTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $_SESSION = [];
    }

    /**
     * Кожен рендер форми отримує власний токен. Один спільний був би пасткою:
     * форма зворотного дзвінка стоїть на кожній сторінці, тож після першої
     * заявки кожна раніше відкрита вкладка тримала б мертвий токен.
     */
    public function testEachRenderGetsItsOwnToken()
    {
        $first  = FormToken::get('callback');
        $second = FormToken::get('callback');

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $first);
        $this->assertNotSame($first, $second);
    }

    public function testTwoTabsBothGoThrough()
    {
        $tabOne = FormToken::get('callback');
        $tabTwo = FormToken::get('callback');

        $this->assertTrue(FormToken::consume('callback', $tabOne));
        $this->assertTrue(FormToken::consume('callback', $tabTwo));
    }

    /**
     * Сторінка, відкрита давно, теж має спрацювати: її токен ніхто не
     * використав, тож це не повтор, хай і виданий він був годину тому.
     */
    public function testAnOldUnusedTokenStillWorks()
    {
        $old = FormToken::get('callback');

        for ($i = 0; $i < 5; $i++) {
            FormToken::consume('callback', FormToken::get('callback'));
        }

        $this->assertTrue(FormToken::consume('callback', $old));
    }

    public function testTokenWorksExactlyOnce()
    {
        $token = FormToken::get('callback');

        $this->assertTrue(FormToken::consume('callback', $token));
        $this->assertFalse(FormToken::consume('callback', $token));
    }

    /**
     * Перелік використаних - свій у кожної форми: заявка на дзвінок не має
     * гасити коментар, відправлений із тієї ж картки товару.
     */
    public function testFormsDoNotShareTheUsedList()
    {
        $token = FormToken::get('callback');

        $this->assertTrue(FormToken::consume('callback', $token));
        $this->assertFalse(FormToken::consume('callback', $token));
        $this->assertTrue(FormToken::consume('comment', $token));
    }

    public function testMalformedTokensAreRefused()
    {
        $this->assertFalse(FormToken::consume('callback', null));
        $this->assertFalse(FormToken::consume('callback', ''));
        $this->assertFalse(FormToken::consume('callback', 'коротко'));
        $this->assertFalse(FormToken::consume('callback', str_repeat('ы', 64)));
    }

    /**
     * Перелік обмежений, інакше сесія росла б без краю. Переповнення означає,
     * що дуже давній повтор проскочить - це дешевше за втрату даних.
     */
    /**
     * Повтор має привести на те замовлення, яке створив саме цей токен, а не
     * на «останнє замовлення сесії» - воно могло бути й від іншої вкладки.
     */
    public function testResultIsRecalledByTheTokenThatCreatedIt()
    {
        $first  = FormToken::get('fast_order');
        $second = FormToken::get('fast_order');

        FormToken::consume('fast_order', $first);
        FormToken::remember('fast_order', $first, 'order-a');
        FormToken::consume('fast_order', $second);
        FormToken::remember('fast_order', $second, 'order-b');

        $this->assertSame('order-a', FormToken::recall('fast_order', $first));
        $this->assertSame('order-b', FormToken::recall('fast_order', $second));
    }

    /**
     * Токен витрачено, але результату немає - попередню спробу обірвало вже
     * після зняття токена. Викликач має вміти відрізнити це від повтору.
     */
    public function testAbortedAttemptLeavesNoResult()
    {
        $token = FormToken::get('fast_order');
        FormToken::consume('fast_order', $token);

        $this->assertFalse(FormToken::consume('fast_order', $token));
        $this->assertNull(FormToken::recall('fast_order', $token));
    }

    public function testShortWindowLetsADeliberateRepeatThrough()
    {
        $payload = (object)['name' => 'Іван', 'phone' => '380670000000'];

        $this->assertTrue(FormToken::accept('callback', null, $payload, FormToken::ACCIDENT_TTL));
        $this->assertFalse(FormToken::accept('callback', null, $payload, FormToken::ACCIDENT_TTL));

        $_SESSION[FormToken::FINGERPRINT_KEY]['callback']['expires_at'] = time() - 1;

        $this->assertTrue(FormToken::accept('callback', null, $payload, FormToken::ACCIDENT_TTL));
    }

    public function testTheUsedListIsBounded()
    {
        $first = FormToken::get('callback');
        FormToken::consume('callback', $first);

        for ($i = 0; $i < FormToken::MAX_USED; $i++) {
            FormToken::consume('callback', FormToken::get('callback'));
        }

        $this->assertCount(FormToken::MAX_USED, $_SESSION[FormToken::SESSION_KEY]['callback']);
        $this->assertTrue(FormToken::consume('callback', $first));
    }

    public function testSameSubmissionTwiceIsRefusedByFingerprint()
    {
        $payload = (object)['name' => 'Іван', 'phone' => '380670000000'];

        $fingerprint = FormToken::fingerprintOf($payload);

        $this->assertTrue(FormToken::consumeFingerprint('callback', $fingerprint));
        $this->assertFalse(FormToken::consumeFingerprint('callback', $fingerprint));
    }

    public function testDifferentSubmissionPassesTheFingerprint()
    {
        $first  = FormToken::fingerprintOf((object)['name' => 'Іван']);
        $second = FormToken::fingerprintOf((object)['name' => 'Марія']);

        $this->assertTrue(FormToken::consumeFingerprint('callback', $first));
        $this->assertTrue(FormToken::consumeFingerprint('callback', $second));
    }

    public function testFingerprintsDoNotLeakBetweenForms()
    {
        $fingerprint = FormToken::fingerprintOf((object)['name' => 'Іван']);

        $this->assertTrue(FormToken::consumeFingerprint('callback', $fingerprint));
        $this->assertTrue(FormToken::consumeFingerprint('feedback', $fingerprint));
    }

    /**
     * Оформлення додає до відбитка склад кошика: те саме ім'я з тим самим
     * телефоном, але з іншим набором товарів - нове замовлення.
     */
    public function testCartContentsChangeTheFingerprint()
    {
        $order = (object)['name' => 'Іван'];

        $first  = FormToken::fingerprintOf([$order, [(object)['variant_id' => 7, 'amount' => 2]]]);
        $second = FormToken::fingerprintOf([$order, [(object)['variant_id' => 7, 'amount' => 3]]]);

        $this->assertTrue(FormToken::consumeFingerprint('checkout', $first));
        $this->assertTrue(FormToken::consumeFingerprint('checkout', $second));
    }

    /**
     * Порядок ключів у формі не має створювати новий відбиток: інакше та сама
     * заявка з перетасованими полями проходила б як нова.
     */
    public function testKeyOrderDoesNotChangeTheFingerprint()
    {
        $first  = FormToken::fingerprintOf(['name' => 'Іван', 'phone' => '380670000000']);
        $second = FormToken::fingerprintOf(['phone' => '380670000000', 'name' => 'Іван']);

        $this->assertSame($first, $second);
    }

    public function testAcceptTakesTheTokenPathWhenTheThemeSendsOne()
    {
        $payload = (object)['name' => 'Іван', 'phone' => '380670000000'];
        $token = FormToken::get('callback');

        $this->assertTrue(FormToken::accept('callback', $token, $payload));
        $this->assertFalse(FormToken::accept('callback', $token, $payload));
    }

    public function testAcceptFallsBackToTheFingerprintWithoutAToken()
    {
        $payload = (object)['name' => 'Іван'];

        $this->assertTrue(FormToken::accept('callback', null, $payload));
        $this->assertFalse(FormToken::accept('callback', null, $payload));
    }

    /**
     * Відбиток рахується з даних, які сама дія може змінити: оформлення
     * очищає кошик, тож у другого запиту склад уже інший. Якби токен, що не
     * збігся, падав на відбиток, повтор оформлення проходив би як нове
     * замовлення - на стенді це давало два замовлення й чотири листи.
     */
    public function testTokenPathDoesNotFallBackToTheFingerprint()
    {
        $token = FormToken::get('checkout');
        $order = (object)['name' => 'Іван'];

        $this->assertTrue(FormToken::accept('checkout', $token, [$order, ['7' => 2]]));
        $this->assertFalse(FormToken::accept('checkout', $token, [$order, []]));
    }

    public function testExpiredFingerprintNoLongerBlocks()
    {
        $fingerprint = FormToken::fingerprintOf((object)['name' => 'Іван']);

        FormToken::consumeFingerprint('callback', $fingerprint);
        $_SESSION[FormToken::FINGERPRINT_KEY]['callback']['expires_at'] = time() - 1;

        $this->assertTrue(FormToken::consumeFingerprint('callback', $fingerprint));
    }
}
