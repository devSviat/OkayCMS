<?php

namespace Core\Import;

use Okay\Core\Import\CsvQuoteDamage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Приклади — справжні значення «Сумісності» з копії прод-бази broken. */
class CsvQuoteDamageTest extends TestCase
{
    #[DataProvider('damaged')]
    public function testDamagedValueIsRepaired(string $damaged, string $expected): void
    {
        self::assertTrue(CsvQuoteDamage::isDamaged($damaged));
        self::assertSame($expected, CsvQuoteDamage::repair($damaged));
        self::assertFalse(CsvQuoteDamage::isDamaged(CsvQuoteDamage::repair($damaged)));
    }

    public static function damaged(): array
    {
        return [
            'слеш і подвоєна лапка' => ['EN520.BL LATTISSIMA \\"PLUS\\""', 'EN520.BL LATTISSIMA "PLUS"'],
            'подвоєні лапки' => ['EN520.S LATTISSIMA ""PLUS""', 'EN520.S LATTISSIMA "PLUS"'],
            'з\'їдена відкривальна' => ['EN520.BL LATTISSIMA \\PLUS\\"""', 'EN520.BL LATTISSIMA "PLUS"'],
            'двадцять лапок' => ['EN520.BL LATTISIMA \\""""""""""""""""""""""PLUS\\""""""""""""""""""""""', 'EN520.BL LATTISIMA "PLUS"'],
            'хвіст без пари' => ['PULSE EN 110.B"""""""', 'PULSE EN 110.B'],
            'слеш і хвіст' => ['2 fori\\"""', '2 fori'],
        ];
    }

    #[DataProvider('clean')]
    public function testCleanValueIsLeftAlone(string $value): void
    {
        self::assertFalse(CsvQuoteDamage::isDamaged($value));
        self::assertSame($value, CsvQuoteDamage::repair($value));
    }

    public static function clean(): array
    {
        return [
            'лапки в парі' => ['EN520.BL LATTISSIMA "PLUS"'],
            'слеш у моделі — дані джерела, не CSV' => ['KRSC96\\I'],
            'звичайне' => ['S11'],
        ];
    }
}
