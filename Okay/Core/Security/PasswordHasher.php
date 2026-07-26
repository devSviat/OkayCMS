<?php

namespace Okay\Core\Security;

/**
 * Единая точка проверки и создания хешей паролей.
 *
 * Новые пароли всегда создаются через password_hash(). Старые форматы
 * (APR1-MD5, salted MD5, raw MD5) проверяются только для обратной
 * совместимости и должны быть перехешированы после успешного входа.
 */
class PasswordHasher
{
    /** Хеш в формате APR1-MD5: $apr1$<salt 1-8>$<22 символа> */
    const APR1_PATTERN = '/^\$apr1\$([.\/0-9A-Za-z]{1,8})\$[.\/0-9A-Za-z]{22}$/';

    /** Любой MD5-хеш (salted или raw) */
    const MD5_PATTERN = '/^[0-9a-f]{32}$/i';

    public function hash($password)
    {
        $password = (string)$password;

        if (defined('PASSWORD_ARGON2ID')) {
            return password_hash($password, PASSWORD_ARGON2ID);
        }

        return password_hash($password, PASSWORD_BCRYPT);
    }

    public function verify($password, $hash, $legacySalt = null)
    {
        $password = (string)$password;
        $hash = (string)$hash;

        if ($password === '' || $hash === '') {
            return false;
        }

        if ($this->isModernHash($hash)) {
            return password_verify($password, $hash);
        }

        if (preg_match(self::APR1_PATTERN, $hash, $matches)) {
            return hash_equals($hash, $this->cryptApr1Md5($password, $matches[1]));
        }

        if (preg_match(self::MD5_PATTERN, $hash)) {
            if ($legacySalt !== null
                && hash_equals(strtolower($hash), md5($legacySalt . $password . md5($password)))
            ) {
                return true;
            }

            return hash_equals(strtolower($hash), md5($password));
        }

        return false;
    }

    public function needsRehash($hash)
    {
        $hash = (string)$hash;

        if ($hash === '') {
            return false;
        }

        if (!$this->isModernHash($hash)) {
            return true;
        }

        if (defined('PASSWORD_ARGON2ID')) {
            return password_needs_rehash($hash, PASSWORD_ARGON2ID);
        }

        return password_needs_rehash($hash, PASSWORD_BCRYPT);
    }

    public function isModernHash($hash)
    {
        $info = password_get_info((string)$hash);

        return !empty($info['algo']);
    }

    /**
     * Оставлено для проверки существующих APR1-хешей.
     * Новые пароли этим методом не создаются.
     */
    public function cryptApr1Md5($plainpasswd, $salt = '')
    {
        if (empty($salt)) {
            $salt = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 8);
        }
        $len = strlen($plainpasswd);
        $text = $plainpasswd . '$apr1$' . $salt;
        $bin = pack('H32', md5($plainpasswd . $salt . $plainpasswd));
        for ($i = $len; $i > 0; $i -= 16) {
            $text .= substr($bin, 0, min(16, $i));
        }
        for ($i = $len; $i > 0; $i >>= 1) {
            $text .= ($i & 1) ? chr(0) : $plainpasswd[0];
        }
        $bin = pack('H32', md5($text));
        for ($i = 0; $i < 1000; $i++) {
            $new = ($i & 1) ? $plainpasswd : $bin;
            if ($i % 3) {
                $new .= $salt;
            }
            if ($i % 7) {
                $new .= $plainpasswd;
            }
            $new .= ($i & 1) ? $bin : $plainpasswd;
            $bin = pack('H32', md5($new));
        }
        $tmp = '';
        for ($i = 0; $i < 5; $i++) {
            $k = $i + 6;
            $j = $i + 12;
            if ($j == 16) {
                $j = 5;
            }
            $tmp = $bin[$i] . $bin[$k] . $bin[$j] . $tmp;
        }
        $tmp = chr(0) . chr(0) . $bin[11] . $tmp;
        $tmp = strtr(
            strrev(substr(base64_encode($tmp), 2)),
            'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/',
            './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'
        );

        return '$' . 'apr1' . '$' . $salt . '$' . $tmp;
    }
}
