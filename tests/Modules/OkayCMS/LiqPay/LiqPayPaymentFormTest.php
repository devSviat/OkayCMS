<?php

namespace Modules\OkayCMS\LiqPay;

use PHPUnit\Framework\TestCase;

/**
 * Дефект стався не в LiqPayProtocol, а в PaymentForm: саме там приватний ключ
 * клали в масив, що йде у форму. LiqPayProtocolTest доводить, що payload()
 * ключ викидає, — але не заважає викликачеві зібрати payload повз нього.
 *
 * Перевірено мутацією: якщо повернути в PaymentForm ручне складання
 * base64(json(... 'private_key' ...)), увесь набір тестів лишався зеленим.
 *
 * Тест навмисно дивиться на текст методу, а не викликає його: checkoutForm()
 * тягне Design, статичний Router::generateUrl(), EntityFactory і Money — у
 * юніт-тесті це не піднімається. Перевірка форми коду тут виправдана саме
 * тому, що стереже форму дефекту: пару ключ-значення в масиві й обхід
 * протоколу. Поведінкова перевірка робиться на живому оточенні —
 * див. docs/UPGRADE-security.md, розділ 21.
 */
class LiqPayPaymentFormTest extends TestCase
{
    private function source(): string
    {
        $source = file_get_contents(
            dirname(__DIR__, 4) . '/Okay/Modules/OkayCMS/LiqPay/PaymentForm.php'
        );
        $this->assertIsString($source);

        return $source;
    }

    public function testTheFormNeverMentionsThePrivateKeyAsAPayloadField(): void
    {
        $this->assertStringNotContainsString("'private_key'", $this->source());
    }

    /**
     * Payload і підпис мусять іти через протокол — інакше запобіжник
     * LiqPayProtocol::payload(), який безумовно викидає private_key, просто
     * обходиться.
     */
    public function testThePayloadAndSignatureGoThroughTheProtocol(): void
    {
        $source = $this->source();

        $this->assertStringContainsString('$this->protocol->payload(', $source);
        $this->assertStringContainsString('$this->protocol->sign(', $source);
    }

    /**
     * Ручне складання payload'а повз протокол — саме та форма, у якій дефект
     * існував і в яку він може повернутись.
     */
    public function testThePayloadIsNotAssembledByHand(): void
    {
        $source = $this->source();

        $this->assertDoesNotMatchRegularExpression(
            '#base64_encode\s*\(\s*json_encode#',
            $source,
            'payload мусить збирати LiqPayProtocol, а не сам PaymentForm'
        );
        $this->assertDoesNotMatchRegularExpression(
            '#base64_encode\s*\(\s*sha1#',
            $source,
            'підпис мусить рахувати LiqPayProtocol, а не сам PaymentForm'
        );
    }

    /**
     * Приватний ключ у методі лишається — ним підписують. Якщо він зникне
     * зовсім, це означатиме, що підпис рахується не тим ключем.
     */
    public function testThePrivateKeyIsStillReadForSigning(): void
    {
        $source = $this->source();

        $this->assertStringContainsString("\$privateKey = \$settings['liq_pay_private_key']", $source);
        $this->assertStringContainsString('$this->protocol->sign($privateKey', $source);
    }
}
