<?php

namespace Okay\Core\Security;

use Okay\Core\Config;

/**
 * Ключ шифрування секретів 2FA: 32 байти в Base64 (`openssl rand -base64 32`).
 *
 * Звідки (перше непорожнє): змінна оточення OKAY_TOTP_KEY, тоді `totp_key` у
 * config/config.local.php. Хибна змінна оточення не підміняється конфігом
 * мовчки: помилка в налаштуванні має бути видна, а не ховатись за запасним
 * ключем, яким секрети потім не розшифруються.
 */
class TotpKey
{
    public const ENV = 'OKAY_TOTP_KEY';
    public const CONFIG = 'totp_key';

    public const OK = 'ok';
    public const MISSING = 'missing';
    public const INVALID = 'invalid';
    public const NO_SODIUM = 'no_sodium';

    public function __construct(private Config $config)
    {
    }

    /** Сирі 32 байти або null. */
    public function key(): ?string
    {
        return $this->resolve()['key'];
    }

    /** self::OK | MISSING | INVALID | NO_SODIUM */
    public function status(): string
    {
        return $this->resolve()['status'];
    }

    /** 'env' | 'config' | null — для підказки в адмінці, без самого значення. */
    public function source(): ?string
    {
        return $this->resolve()['source'];
    }

    /** @return array{key: ?string, status: string, source: ?string} */
    private function resolve(): array
    {
        if (!function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt')) {
            return ['key' => null, 'status' => self::NO_SODIUM, 'source' => null];
        }

        $fromEnv = getenv(self::ENV);
        if (is_string($fromEnv) && trim($fromEnv) !== '') {
            return self::decode($fromEnv, 'env');
        }

        $fromConfig = $this->config->get(self::CONFIG);
        if (is_string($fromConfig) && trim($fromConfig) !== '') {
            return self::decode($fromConfig, 'config');
        }

        return ['key' => null, 'status' => self::MISSING, 'source' => null];
    }

    /** @return array{key: ?string, status: string, source: ?string} */
    private static function decode(string $encoded, string $source): array
    {
        $key = base64_decode(trim($encoded), true);
        if (!is_string($key) || strlen($key) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
            return ['key' => null, 'status' => self::INVALID, 'source' => $source];
        }

        return ['key' => $key, 'status' => self::OK, 'source' => $source];
    }
}
