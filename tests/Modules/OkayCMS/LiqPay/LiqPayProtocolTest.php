<?php

namespace Modules\OkayCMS\LiqPay;

use Okay\Modules\OkayCMS\LiqPay\LiqPayProtocol;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * PaymentForm клав private_key у data_array, а той іде у форму як base64 —
 * тобто ключ читався з вихідного коду сторінки оплати. Тим самим ключем
 * підписується колбек.
 */
class LiqPayProtocolTest extends TestCase
{
    private const PRIVATE_KEY = 'test_private_key';

    private LiqPayProtocol $protocol;

    protected function setUp(): void
    {
        $this->protocol = new LiqPayProtocol();
    }

    /** Вектор порахований окремо, а не тим самим виразом, що в реалізації. */
    public function testSignFollowsTheDocumentedFormula(): void
    {
        $data = 'eyJ2ZXJzaW9uIjozLCJwdWJsaWNfa2V5IjoiaTAwMDAwMDAwIiwiYWN0aW9uIjoicGF5In0=';

        $this->assertSame(
            'HA089Xwtg9z+YYhpnWVoK2QlY0s=',
            $this->protocol->sign(self::PRIVATE_KEY, $data)
        );
    }

    public function testMatchesAcceptsTheOwnSignature(): void
    {
        $data = 'ZGF0YQ==';

        $this->assertTrue($this->protocol->matches(
            self::PRIVATE_KEY,
            $data,
            $this->protocol->sign(self::PRIVATE_KEY, $data)
        ));
    }

    #[DataProvider('wrongSignatureProvider')]
    public function testMatchesRejectsEverythingElse(string $signature): void
    {
        $this->assertFalse($this->protocol->matches(self::PRIVATE_KEY, 'ZGF0YQ==', $signature));
    }

    public static function wrongSignatureProvider(): array
    {
        return [
            'empty'            => [''],
            'zero'             => ['0'],
            'signed elsewhere' => ['3dyOToJPM5H52ByDTf1BYG6YAGQ='],
            'truncated'        => ['3dyOToJPM5H52ByDTf1BYG6YAG'],
        ];
    }

    public function testMatchesRejectsASignatureMadeWithAnotherKey(): void
    {
        $data = 'ZGF0YQ==';
        $foreign = $this->protocol->sign('someone_elses_key', $data);

        $this->assertFalse($this->protocol->matches(self::PRIVATE_KEY, $data, $foreign));
    }

    #[DataProvider('orderIdProvider')]
    public function testExtractOrderId(string $liqPayOrderId, int $expected): void
    {
        $this->assertSame($expected, $this->protocol->extractOrderId($liqPayOrderId));
    }

    /** Усе, що не має форми "<id>-<число>", дає 0 — як і старий substr/strpos. */
    public static function orderIdProvider(): array
    {
        return [
            'normal'          => ['12-345678', 12],
            'leading zeros'   => ['0012-345678', 12],
            'no separator'    => ['12', 0],
            'empty'           => ['', 0],
            'separator first' => ['-345678', 0],
            'not a number'    => ['abc-345678', 0],
        ];
    }

    /** Ключа немає в payload у жодному вигляді: ні сирим, ні в base64, ні в json. */
    public function testPayloadNeverCarriesThePrivateKey(): void
    {
        $payload = $this->protocol->payload('i00000000', [
            'action'      => 'pay',
            'amount'      => 100.5,
            'currency'    => 'UAH',
            'description' => 'Оплата замовлення №12',
            'order_id'    => '12-345678',
        ]);

        $decoded = base64_decode($payload);

        $this->assertStringNotContainsString(self::PRIVATE_KEY, $payload);
        $this->assertStringNotContainsString(self::PRIVATE_KEY, $decoded);
        $this->assertStringNotContainsString(base64_encode(self::PRIVATE_KEY), $payload);
        $this->assertArrayNotHasKey('private_key', json_decode($decoded, true));
    }

    /** Дефект повертається однією парою ключ-значення, тож payload її викидає. */
    public function testPayloadDropsAPrivateKeyHandedInByTheCaller(): void
    {
        $payload = $this->protocol->payload('i00000000', [
            'action'      => 'pay',
            'private_key' => self::PRIVATE_KEY,
        ]);

        $decoded = base64_decode($payload);

        $this->assertStringNotContainsString(self::PRIVATE_KEY, $decoded);
        $this->assertArrayNotHasKey('private_key', json_decode($decoded, true));
    }

    public function testPayloadCarriesTheProtocolVersionAndPublicKey(): void
    {
        $decoded = json_decode(base64_decode($this->protocol->payload('i00000000', ['action' => 'pay'])), true);

        $this->assertSame(3, $decoded['version']);
        $this->assertSame('i00000000', $decoded['public_key']);
        $this->assertSame('pay', $decoded['action']);
    }

    /** Кирилиця має доїхати текстом, а не \uXXXX: інакше опис нечитабельний. */
    public function testPayloadKeepsUnicodeReadable(): void
    {
        $payload = $this->protocol->payload('i00000000', ['description' => 'Оплата замовлення №12']);

        $this->assertStringContainsString('Оплата замовлення №12', base64_decode($payload));
    }
}
