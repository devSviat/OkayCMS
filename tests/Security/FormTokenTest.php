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

    public function testTokenIsIssuedAndStable()
    {
        $token = FormToken::get('callback');

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
        $this->assertSame($token, FormToken::get('callback'));
    }

    public function testTokenWorksExactlyOnce()
    {
        $token = FormToken::get('callback');

        $this->assertTrue(FormToken::consume('callback', $token));
        $this->assertFalse(FormToken::consume('callback', $token));
    }

    public function testNextSubmissionGetsAFreshToken()
    {
        $first = FormToken::get('callback');
        FormToken::consume('callback', $first);

        $second = FormToken::get('callback');

        $this->assertNotSame($first, $second);
        $this->assertTrue(FormToken::consume('callback', $second));
    }

    /**
     * Форми на одній сторінці незалежні: заявка на дзвінок не має гасити
     * коментар, відправлений із тієї ж картки товару.
     */
    public function testFormsDoNotShareTokens()
    {
        $callback = FormToken::get('callback');
        $comment  = FormToken::get('comment');

        $this->assertNotSame($callback, $comment);
        $this->assertFalse(FormToken::consume('comment', $callback));
        $this->assertTrue(FormToken::consume('callback', $callback));
        $this->assertTrue(FormToken::consume('comment', $comment));
    }

    public function testMalformedAndForeignTokensAreRefused()
    {
        FormToken::get('callback');

        $this->assertFalse(FormToken::consume('callback', null));
        $this->assertFalse(FormToken::consume('callback', ''));
        $this->assertFalse(FormToken::consume('callback', 'коротко'));
        $this->assertFalse(FormToken::consume('callback', str_repeat('a', 64)));
    }

    public function testConsumeWithoutAnIssuedTokenIsRefused()
    {
        $this->assertFalse(FormToken::consume('callback', str_repeat('a', 64)));
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
