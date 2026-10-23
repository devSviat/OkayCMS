<?php

namespace Security;

use Okay\Core\Security\Totp;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TotpTest extends TestCase
{
    /** RFC 6238, додаток B, SHA1: секрет "12345678901234567890", 8 цифр. */
    public static function rfcVectors(): array
    {
        return [
            [59, '94287082'],
            [1111111109, '07081804'],
            [1111111111, '14050471'],
            [1234567890, '89005924'],
            [2000000000, '69279037'],
            [20000000000, '65353130'],
        ];
    }

    #[DataProvider('rfcVectors')]
    public function testRfc6238Vectors(int $time, string $expected): void
    {
        $this->assertSame($expected, Totp::code('12345678901234567890', Totp::step($time), 8));
    }

    /** Шість цифр — останні шість від восьми: так рахує будь-який застосунок. */
    public function testSixDigitsAreTheTailOfTheRfcCode(): void
    {
        $this->assertSame('287082', Totp::code('12345678901234567890', Totp::step(59)));
    }

    public function testMatchingStepAcceptsOneStepOfClockDriftOnly(): void
    {
        $secret = '12345678901234567890';
        $now = 1_700_000_000;
        $step = Totp::step($now);

        $this->assertSame($step, Totp::matchingStep($secret, Totp::code($secret, $step), $now));
        $this->assertSame($step - 1, Totp::matchingStep($secret, Totp::code($secret, $step - 1), $now));
        $this->assertSame($step + 1, Totp::matchingStep($secret, Totp::code($secret, $step + 1), $now));
        $this->assertNull(Totp::matchingStep($secret, Totp::code($secret, $step - 2), $now));
        $this->assertNull(Totp::matchingStep($secret, Totp::code($secret, $step + 2), $now));
    }

    public function testOnlySixDigitsAreACode(): void
    {
        $secret = '12345678901234567890';
        $now = 1_700_000_000;
        $code = Totp::code($secret, Totp::step($now));

        $this->assertNull(Totp::matchingStep($secret, $code . ' ', $now));
        $this->assertNull(Totp::matchingStep($secret, ' ' . $code, $now));
        $this->assertNull(Totp::matchingStep($secret, '', $now));
        $this->assertNull(Totp::matchingStep($secret, substr($code, 0, 5), $now));
    }

    /** RFC 4648: "foobar" → "MZXW6YTBOI". */
    public function testBase32RoundTrip(): void
    {
        $this->assertSame('MZXW6YTBOI', Totp::base32Encode('foobar'));
        $this->assertSame('foobar', Totp::base32Decode('MZXW6YTBOI'));
        $this->assertSame('foobar', Totp::base32Decode('mzxw 6ytb oi======'));
        $this->assertNull(Totp::base32Decode('MZXW1'), '1 не з алфавіту Base32');

        $secret = Totp::newSecret();
        $this->assertSame(Totp::SECRET_BYTES, strlen($secret));
        $this->assertSame($secret, Totp::base32Decode(Totp::base32Encode($secret)));
    }

    public function testProvisioningUriEscapesIssuerAndAccount(): void
    {
        $uri = Totp::provisioningUri('foobar', 'Мій магазин', 'adm:in');

        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString('secret=MZXW6YTBOI', $uri);
        $this->assertStringContainsString(':adm%3Ain?', $uri, 'двокрапка в логіні не ламає мітку');
        $this->assertStringNotContainsString(' ', $uri);
    }
}
