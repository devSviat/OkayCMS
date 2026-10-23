<?php

namespace Okay\Core\Security;

/**
 * Ключ клієнта для лімітів спроб.
 */
final class ClientIp
{
    /**
     * IPv4 — як є, IPv6 — за /64: одна мережа має їх мільярди, і кожна адреса
     * інакше давала б новий повний ліміт.
     */
    public static function bucket(string $ip): string
    {
        $packed = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? inet_pton($ip) : false;
        if ($packed !== false) {
            return bin2hex(substr($packed, 0, 8)) . '::/64';
        }

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'unknown';
    }

    public static function current(): string
    {
        return self::bucket((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    }
}
