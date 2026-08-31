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

    /**
     * Головна властивість підпису: підроблений колбек не проходить. Кожен
     * випадок тут — окрема спроба заробити на магазині, а не варіація формату.
     */
    #[DataProvider('tamperedPayloadProvider')]
    public function testTamperedPayloadProducesADifferentSignature(array $tampered): void
    {
        $original = base64_encode(json_encode([
            'order_id' => '42-123456',
            'status'   => 'success',
            'amount'   => 100.00,
            'currency' => 'UAH',
        ]));

        $this->assertNotSame(
            $this->protocol->sign(self::PRIVATE_KEY, $original),
            $this->protocol->sign(self::PRIVATE_KEY, base64_encode(json_encode($tampered)))
        );
    }

    public static function tamperedPayloadProvider(): array
    {
        return [
            'занижена сума' => [
                ['order_id' => '42-123456', 'status' => 'success', 'amount' => 1.00, 'currency' => 'UAH'],
            ],
            'чуже замовлення' => [
                ['order_id' => '99-123456', 'status' => 'success', 'amount' => 100.00, 'currency' => 'UAH'],
            ],
            'підмінений статус' => [
                ['order_id' => '42-123456', 'status' => 'failure', 'amount' => 100.00, 'currency' => 'UAH'],
            ],
            'інша валюта' => [
                ['order_id' => '42-123456', 'status' => 'success', 'amount' => 100.00, 'currency' => 'USD'],
            ],
        ];
    }

    /**
     * Ключ обгортає дані з обох боків. Підпис лише з переднім ключем — робоча
     * реалізація hmac-подібної схеми, яку LiqPay відхилить мовчки: замовлення
     * просто не оплатяться.
     */
    public function testKeyWrapsTheDataOnBothSides(): void
    {
        $data = 'ZGF0YQ==';

        $this->assertNotSame(
            base64_encode(sha1(self::PRIVATE_KEY . $data, true)),
            $this->protocol->sign(self::PRIVATE_KEY, $data)
        );
    }

    /** sha1 у сирих байтах — 20 байтів, тобто рівно 28 символів base64. */
    public function testSignatureHasTheShapeOfRawSha1(): void
    {
        $signature = $this->protocol->sign(self::PRIVATE_KEY, 'ZGF0YQ==');

        $this->assertSame(28, strlen($signature));
        $this->assertMatchesRegularExpression('~^[A-Za-z0-9+/]+=*$~', $signature);
    }

    /** Порожній ключ не має збігатися з непорожнім — інакше пусті налаштування «працюють». */
    public function testEmptyKeyDoesNotCollide(): void
    {
        $this->assertNotSame(
            $this->protocol->sign('', 'ZGF0YQ=='),
            $this->protocol->sign(self::PRIVATE_KEY, 'ZGF0YQ==')
        );
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
