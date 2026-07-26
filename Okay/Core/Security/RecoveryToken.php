<?php

namespace Okay\Core\Security;

/**
 * Токен відновлення пароля покупця.
 *
 * У лист іде непрозорий токен, у базу пишеться лише його digest.
 * Digest обрізаний до 32 символів, бо колонка ok_users.remind_code
 * оголошена як varchar(32); 128 біт для цієї задачі достатньо.
 */
class RecoveryToken
{
    /** Час життя токена в секундах */
    const TTL = 300;

    const TOKEN_PATTERN = '/^[0-9a-f]{64}$/';

    public function create()
    {
        return bin2hex(random_bytes(32));
    }

    public function digest($token)
    {
        return substr(hash('sha256', (string)$token), 0, 32);
    }

    public function isValidFormat($token)
    {
        if (!is_string($token)) {
            return false;
        }

        return (bool)preg_match(self::TOKEN_PATTERN, $token);
    }

    public function expiresAt($now = null)
    {
        if ($now === null) {
            $now = time();
        }

        return date('Y-m-d H:i:s', (int)$now + self::TTL);
    }
}
