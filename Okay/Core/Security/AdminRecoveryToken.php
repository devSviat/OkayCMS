<?php

namespace Okay\Core\Security;

use Okay\Core\Config;

/**
 * Токен восстановления пароля менеджера.
 *
 * Таблица ok_managers не имеет колонок под восстановление, поэтому токен
 * не хранится: он подписан HMAC-ом и привязан к текущему хешу пароля
 * менеджера. Как только пароль изменён, старый токен становится
 * недействительным — это даёт одноразовость без хранилища.
 */
class AdminRecoveryToken
{
    /** Время жизни токена в секундах */
    const TTL = 3600;

    /** @var string */
    private $key;

    public function __construct(Config $config)
    {
        $this->key = (string)$config->salt;
    }

    public function create($managerId, $currentPasswordHash, $now = null)
    {
        if ($now === null) {
            $now = time();
        }

        $managerId = (int)$managerId;
        $expires = (int)$now + self::TTL;
        $payload = $this->encode($managerId . ':' . $expires);

        return $payload . '.' . $this->sign($managerId, $expires, $currentPasswordHash);
    }

    public function unverifiedManagerId($token)
    {
        $parts = $this->parse($token);

        return $parts === null ? null : $parts['manager_id'];
    }

    public function managerId($token, $currentPasswordHash, $now = null)
    {
        if ($now === null) {
            $now = time();
        }

        $parts = $this->parse($token);
        if ($parts === null) {
            return null;
        }

        if ($parts['expires'] < (int)$now) {
            return null;
        }

        $expected = $this->sign($parts['manager_id'], $parts['expires'], $currentPasswordHash);
        if (!hash_equals($expected, $parts['signature'])) {
            return null;
        }

        return $parts['manager_id'];
    }

    private function parse($token)
    {
        if (!is_string($token) || strpos($token, '.') === false) {
            return null;
        }

        list($payload, $signature) = explode('.', $token, 2);

        $decoded = $this->decode($payload);
        if ($decoded === null || strpos($decoded, ':') === false) {
            return null;
        }

        list($managerId, $expires) = explode(':', $decoded, 2);

        if (!ctype_digit($managerId) || !ctype_digit($expires)) {
            return null;
        }

        return [
            'manager_id' => (int)$managerId,
            'expires'    => (int)$expires,
            'signature'  => $signature,
        ];
    }

    private function sign($managerId, $expires, $currentPasswordHash)
    {
        return hash_hmac(
            'sha256',
            $managerId . ':' . $expires . ':' . (string)$currentPasswordHash,
            $this->key
        );
    }

    private function encode($value)
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function decode($value)
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
