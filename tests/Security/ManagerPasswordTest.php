<?php

namespace Security;

use Okay\Core\Managers;
use PHPUnit\Framework\TestCase;

class ManagerPasswordTest extends TestCase
{
    public function testInvalidStoredHashFailsWithoutWarning()
    {
        $managers = new Managers();

        $this->assertFalse($managers->checkPassword('secret', ''));
        $this->assertFalse($managers->checkPassword('secret', 'not-a-hash'));
        $this->assertFalse($managers->checkPassword('secret', '$broken$hash'));
        $this->assertFalse($managers->checkPassword('secret', '$apr1$12345678$short'));
    }

    public function testHashPasswordProducesModernHash()
    {
        $managers = new Managers();
        $hash = $managers->hashPassword('secret');

        $this->assertMatchesRegularExpression('/^\$(argon2id|2y)\$/', $hash);
        $this->assertTrue($managers->checkPassword('secret', $hash));
        $this->assertFalse($managers->checkPassword('wrong', $hash));
        $this->assertFalse($managers->needsPasswordRehash($hash));
    }

    public function testLegacyApr1HashStillVerifiesAndIsFlaggedForRehash()
    {
        $managers = new Managers();
        $hash = $managers->cryptApr1Md5('secret', '12345678');

        $this->assertTrue($managers->checkPassword('secret', $hash));
        $this->assertTrue($managers->needsPasswordRehash($hash));
    }

    public function testEntityStoresModernHashesForNewAndUpdatedManagers()
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/Okay/Entities/ManagersEntity.php');

        $this->assertIsString($source);
        $this->assertStringNotContainsString('$managersCore->cryptApr1Md5($manager->password)', $source);
        $this->assertSame(2, substr_count($source, '$managersCore->hashPassword($manager->password)'));
    }

    public function testLoginRehashesLegacyManagerPasswords()
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/backend/Controllers/AuthAdmin.php');

        $this->assertIsString($source);
        $this->assertStringContainsString('needsPasswordRehash($manager->password)', $source);
    }
}
