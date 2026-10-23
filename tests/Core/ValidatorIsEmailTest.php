<?php

namespace Core;

use Okay\Core\Recaptcha;
use Okay\Core\Settings;
use Okay\Core\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Без модифікатора D «$» у регулярці пропускав перенос рядка в кінці: акаунт
 * «victim@x.com\n» реєструвався поруч зі справжнім, а код, що обрізає
 * пробіли, бачив у ньому ту саму адресу.
 */
class ValidatorIsEmailTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Validator($this->createStub(Settings::class), $this->createStub(Recaptcha::class));
    }

    /** @return array<string, array{0: string, 1: bool}> */
    public static function emailProvider(): array
    {
        return [
            'звичайна' => ['buyer@example.com', true],
            'з крапкою й дефісом' => ['first.last-1@shop-x.example.ua', true],
            'перенос рядка в кінці' => ["victim@example.com\n", false],
            'перенос рядка всередині' => ["victim@example.com\nbcc@x.com", false],
            'без домену' => ['buyer@', false],
        ];
    }

    #[DataProvider('emailProvider')]
    public function testIsEmail(string $email, bool $expected): void
    {
        $this->assertSame($expected, $this->validator->isEmail($email), json_encode($email));
    }
}
