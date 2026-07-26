<?php

namespace Okay\Core\Security;

/**
 * Токен восстановления пароля покупателя.
 *
 * В письмо уходит непрозрачный токен, в базу пишется только его digest.
 * Digest обрезан до 32 символов, потому что колонка ok_users.remind_code
 * объявлена как varchar(32); 128 бит достаточно для этой задачи.
 */
class RecoveryToken
{
    /** Время жизни токена в секундах */
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
