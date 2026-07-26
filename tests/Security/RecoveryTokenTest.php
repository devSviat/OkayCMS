<?php

namespace Security;

use Okay\Core\Config;
use Okay\Core\Security\AdminRecoveryToken;
use Okay\Core\Security\RecoveryToken;
use PHPUnit\Framework\TestCase;

class RecoveryTokenTest extends TestCase
{
    public function testCustomerTokenIsOpaqueAndDigestIsStorable()
    {
        $token = new RecoveryToken();
        $plain = $token->create();
        $digest = $token->digest($plain);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $plain);
        // Ровно 32 символа: столько вмещает ok_users.remind_code
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $digest);
        $this->assertNotSame($plain, $digest);
        $this->assertSame($digest, $token->digest($plain));
    }

    public function testCustomerTokenFormatValidation()
    {
        $token = new RecoveryToken();

        $this->assertTrue($token->isValidFormat($token->create()));
        $this->assertFalse($token->isValidFormat(null));
        $this->assertFalse($token->isValidFormat(''));
        $this->assertFalse($token->isValidFormat('abc'));
        $this->assertFalse($token->isValidFormat(str_repeat('g', 64)));
    }

    public function testCustomerTokensAreUnique()
    {
        $token = new RecoveryToken();

        $this->assertNotSame($token->create(), $token->create());
    }

    public function testCustomerTokenExpiryUsesTtl()
    {
        $token = new RecoveryToken();
        $now = strtotime('2026-07-26 00:00:00');

        $this->assertSame(
            date('Y-m-d H:i:s', $now + RecoveryToken::TTL),
            $token->expiresAt($now)
        );
    }

    public function testAdminTokenCarriesManagerIdentityWithoutSession()
    {
        $token = new AdminRecoveryToken($this->config());
        $code = $token->create(15, 'current-password-hash', 1000);

        $this->assertSame(15, $token->unverifiedManagerId($code));
        $this->assertSame(15, $token->managerId($code, 'current-password-hash', 1200));
    }

    public function testAdminTokenIsInvalidatedByPasswordChange()
    {
        $token = new AdminRecoveryToken($this->config());
        $code = $token->create(15, 'old-password-hash', 1000);

        $this->assertNull($token->managerId($code, 'new-password-hash', 1200));
    }

    public function testAdminTokenExpires()
    {
        $token = new AdminRecoveryToken($this->config());
        $code = $token->create(15, 'current-password-hash', 1000);

        $this->assertNull($token->managerId($code, 'current-password-hash', 1000 + AdminRecoveryToken::TTL + 1));
    }

    public function testAdminTokenRejectsTamperedPayload()
    {
        $token = new AdminRecoveryToken($this->config());
        $code = $token->create(15, 'current-password-hash', 1000);

        $this->assertNull($token->managerId(str_replace('.', 'x.', $code), 'current-password-hash', 1200));
        $this->assertNull($token->managerId(null, 'current-password-hash', 1200));
        $this->assertNull($token->managerId('garbage', 'current-password-hash', 1200));
        $this->assertNull($token->managerId('', 'current-password-hash', 1200));
    }

    public function testAdminTokenForAnotherManagerDoesNotVerify()
    {
        $token = new AdminRecoveryToken($this->config());
        $code = $token->create(15, 'current-password-hash', 1000);

        // Подменяем полезную нагрузку на другого менеджера: подпись не сойдётся
        $forged = $this->encode('16:' . (1000 + AdminRecoveryToken::TTL)) . '.' . explode('.', $code)[1];

        $this->assertSame(16, $token->unverifiedManagerId($forged));
        $this->assertNull($token->managerId($forged, 'current-password-hash', 1200));
    }

    private function encode($value)
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function config()
    {
        return new class ('', '') extends Config {
            public function __construct(string $configFile, string $configLocalFile)
            {
                $this->salt = 'test-salt';
            }
        };
    }
}
