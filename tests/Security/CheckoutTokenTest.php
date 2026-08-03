<?php

namespace Security;

use Okay\Core\Security\CheckoutToken;
use PHPUnit\Framework\TestCase;

/**
 * CSRF-токен кошика захищає від підробки запиту, але не від повтору того
 * самого: подвійний клік по кнопці оформлення створював два замовлення
 * і два комплекти листів.
 */
class CheckoutTokenTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $_SESSION = [];
    }

    public function testTokenIsIssuedAndStable()
    {
        $token = CheckoutToken::get();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
        $this->assertSame($token, CheckoutToken::get());
    }

    public function testTokenWorksExactlyOnce()
    {
        $token = CheckoutToken::get();

        $this->assertTrue(CheckoutToken::consume($token));
        $this->assertFalse(CheckoutToken::consume($token));
    }

    public function testNextOrderGetsAFreshToken()
    {
        $first = CheckoutToken::get();
        CheckoutToken::consume($first);

        $second = CheckoutToken::get();

        $this->assertNotSame($first, $second);
        $this->assertTrue(CheckoutToken::consume($second));
    }

    public function testMalformedAndForeignTokensAreRefused()
    {
        CheckoutToken::get();

        $this->assertFalse(CheckoutToken::consume(null));
        $this->assertFalse(CheckoutToken::consume(''));
        $this->assertFalse(CheckoutToken::consume('коротко'));
        $this->assertFalse(CheckoutToken::consume(str_repeat('a', 64)));
    }

    public function testConsumeWithoutAnIssuedTokenIsRefused()
    {
        $this->assertFalse(CheckoutToken::consume(str_repeat('a', 64)));
    }

    public function testSameOrderTwiceIsRefusedByFingerprint()
    {
        $order = (object)['name' => 'Іван', 'phone' => '380670000000'];
        $cart = [(object)['variant_id' => 7, 'amount' => 2]];

        $fingerprint = CheckoutToken::fingerprintOf($order, $cart);

        $this->assertTrue(CheckoutToken::consumeFingerprint($fingerprint));
        $this->assertFalse(CheckoutToken::consumeFingerprint($fingerprint));
    }

    public function testDifferentOrderPassesTheFingerprint()
    {
        $cart = [(object)['variant_id' => 7, 'amount' => 2]];

        $first = CheckoutToken::fingerprintOf((object)['name' => 'Іван'], $cart);
        $second = CheckoutToken::fingerprintOf((object)['name' => 'Марія'], $cart);

        $this->assertTrue(CheckoutToken::consumeFingerprint($first));
        $this->assertTrue(CheckoutToken::consumeFingerprint($second));
    }

    public function testDifferentCartPassesTheFingerprint()
    {
        $order = (object)['name' => 'Іван'];

        $first = CheckoutToken::fingerprintOf($order, [(object)['variant_id' => 7, 'amount' => 2]]);
        $second = CheckoutToken::fingerprintOf($order, [(object)['variant_id' => 7, 'amount' => 3]]);

        $this->assertTrue(CheckoutToken::consumeFingerprint($first));
        $this->assertTrue(CheckoutToken::consumeFingerprint($second));
    }

    public function testExpiredFingerprintNoLongerBlocks()
    {
        $fingerprint = CheckoutToken::fingerprintOf((object)['name' => 'Іван'], []);

        CheckoutToken::consumeFingerprint($fingerprint);
        $_SESSION[CheckoutToken::FINGERPRINT_KEY]['expires_at'] = time() - 1;

        $this->assertTrue(CheckoutToken::consumeFingerprint($fingerprint));
    }

    /**
     * Обидві теми, що є в дереві, мають передавати токен: інакше вони
     * мовчки з'їдуть на менш точний запасний шлях.
     */
    public function testShippedThemesSubmitTheToken()
    {
        foreach (['okay_shop', 'vibe_shop'] as $theme) {
            $tpl = file_get_contents(dirname(__DIR__, 2) . '/design/' . $theme . '/html/cart.tpl');

            $this->assertIsString($tpl, $theme);
            $this->assertStringContainsString('name="checkout_token"', $tpl, $theme);
        }
    }
}
