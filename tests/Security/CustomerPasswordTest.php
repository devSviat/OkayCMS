<?php

namespace Security;

use PHPUnit\Framework\TestCase;

class CustomerPasswordTest extends TestCase
{
    public function testEntityHashesThroughPasswordHasher()
    {
        $source = $this->source();

        $this->assertStringContainsString('use Okay\Core\Security\PasswordHasher;', $source);
        $this->assertStringNotContainsString(
            "md5(\$this->salt . \$user['password'] . md5(\$user['password']))",
            $source
        );
    }

    public function testCheckPasswordDoesNotMatchHashesInSql()
    {
        $source = $this->source();

        $this->assertStringNotContainsString("'password' => \$encPassword", $source);
        $this->assertStringContainsString('->verify($password,', $source);
    }

    public function testLegacyHashesAreRehashedAfterSuccessfulCheck()
    {
        $source = $this->source();

        $this->assertStringContainsString('needsRehash', $source);
        $this->assertStringContainsString('updatePasswordHash', $source);
    }

    public function testUpdatePasswordHashBypassesReHashing()
    {
        $source = $this->source();

        $this->assertStringContainsString('function updatePasswordHash', $source);
        $this->assertStringContainsString("parent::update((int)\$userId, ['password' => \$hash]);", $source);
    }

    private function source()
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/Okay/Entities/UsersEntity.php');
        $this->assertIsString($source);

        return $source;
    }
}
