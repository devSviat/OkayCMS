<?php

namespace Security;

use Okay\Core\Security\PasswordHasher;
use PHPUnit\Framework\TestCase;

class PasswordHasherTest extends TestCase
{
    public function testHashProducesModernFormatAndVerifies()
    {
        $hasher = new PasswordHasher();
        $hash = $hasher->hash('secret');

        $this->assertMatchesRegularExpression('/^\$(argon2id|2y)\$/', $hash);
        $this->assertTrue($hasher->verify('secret', $hash));
        $this->assertFalse($hasher->verify('wrong', $hash));
        $this->assertFalse($hasher->needsRehash($hash));
    }

    public function testMalformedStoredHashesAreRejectedWithoutWarnings()
    {
        $hasher = new PasswordHasher();

        $this->assertFalse($hasher->verify('secret', null));
        $this->assertFalse($hasher->verify('secret', ''));
        $this->assertFalse($hasher->verify('secret', 'not-a-hash'));
        $this->assertFalse($hasher->verify('secret', '$broken$hash'));
        $this->assertFalse($hasher->verify('secret', '$apr1$12345678$short'));
    }

    public function testLegacyApr1HashVerifiesAndNeedsRehash()
    {
        $hasher = new PasswordHasher();
        $hash = $hasher->cryptApr1Md5('secret', '12345678');

        $this->assertTrue($hasher->verify('secret', $hash));
        $this->assertFalse($hasher->verify('wrong', $hash));
        $this->assertTrue($hasher->needsRehash($hash));
    }

    public function testLegacySaltedMd5HashVerifiesAndNeedsRehash()
    {
        $hasher = new PasswordHasher();
        $salt = '8e86a279d6e182b3c811c559e6b15484';
        $hash = md5($salt . 'secret' . md5('secret'));

        $this->assertTrue($hasher->verify('secret', $hash, $salt));
        $this->assertFalse($hasher->verify('wrong', $hash, $salt));
        $this->assertTrue($hasher->needsRehash($hash));
    }

    public function testLegacyRawMd5HashVerifies()
    {
        $hasher = new PasswordHasher();
        $hash = md5('secret');

        $this->assertTrue($hasher->verify('secret', $hash));
        $this->assertFalse($hasher->verify('wrong', $hash));
    }

    public function testEmptyPasswordNeverVerifies()
    {
        $hasher = new PasswordHasher();

        $this->assertFalse($hasher->verify('', $hasher->hash('secret')));
        $this->assertFalse($hasher->verify('', md5('')));
    }
}
