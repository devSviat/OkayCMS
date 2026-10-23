<?php

namespace Security;

use Okay\Core\Config;
use Okay\Core\DebugBar\DebugBar;
use Okay\Core\Security\TotpKey;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class TotpKeyTest extends TestCase
{
    private function config(?string $value): Config
    {
        $config = $this->createStub(Config::class);
        $config->method('get')->willReturn($value);

        return $config;
    }

    #[RunInSeparateProcess]
    public function testEnvironmentWinsOverConfig(): void
    {
        $envKey = random_bytes(32);
        putenv(TotpKey::ENV . '=' . base64_encode($envKey));

        $key = new TotpKey($this->config(base64_encode(random_bytes(32))));
        $this->assertSame($envKey, $key->key());
        $this->assertSame('env', $key->source());
    }

    #[RunInSeparateProcess]
    public function testConfigIsTheFallback(): void
    {
        putenv(TotpKey::ENV);
        $configKey = random_bytes(32);

        $key = new TotpKey($this->config(base64_encode($configKey)));
        $this->assertSame($configKey, $key->key());
        $this->assertSame('config', $key->source());
    }

    /** Хибна змінна оточення не ховається за конфігом: помилка має бути видна. */
    #[RunInSeparateProcess]
    public function testInvalidEnvironmentKeyIsNotReplacedByConfig(): void
    {
        putenv(TotpKey::ENV . '=' . base64_encode(random_bytes(16)));

        $key = new TotpKey($this->config(base64_encode(random_bytes(32))));
        $this->assertNull($key->key());
        $this->assertSame(TotpKey::INVALID, $key->status());
    }

    #[RunInSeparateProcess]
    public function testMissingAndMalformedKeys(): void
    {
        putenv(TotpKey::ENV);

        $this->assertSame(TotpKey::MISSING, (new TotpKey($this->config('')))->status());
        $this->assertSame(TotpKey::MISSING, (new TotpKey($this->config(null)))->status());
        $this->assertSame(TotpKey::INVALID, (new TotpKey($this->config('not base64!')))->status());
        $this->assertSame(TotpKey::INVALID, (new TotpKey($this->config(base64_encode(random_bytes(31)))))->status());
    }

    /** Панель відладки показує конфіг на сторінці: ключі й паролі туди не потрапляють. */
    public function testDebugBarMasksSecrets(): void
    {
        $buffer = new \ReflectionProperty(DebugBar::class, 'bufferedConfigValues');
        $before = $buffer->getValue();
        try {
            $buffer->setValue(null, []);
            DebugBar::setConfigValue('totp_key', 'c2VjcmV0', 'local');
            DebugBar::setConfigValue('db_password', 'root', 'local');
            DebugBar::setConfigValue('db_name', 'okay', 'local');

            $values = array_column($buffer->getValue(), 'value', 'name');
            $this->assertSame('***', $values['totp_key']);
            $this->assertSame('***', $values['db_password']);
            $this->assertSame('okay', $values['db_name']);
        } finally {
            $buffer->setValue(null, $before);
        }
    }
}
