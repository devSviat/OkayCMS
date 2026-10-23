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
     *
     * IPv4, записана як IPv6 (`::ffff:203.0.113.7`), — це IPv4: так її віддає
     * сервер на dual-stack сокеті, і за /64 весь IPv4-трафік ділив би один ліміт.
     */
    public static function bucket(string $ip): string
    {
        $packed = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? inet_pton($ip) : false;
        if ($packed !== false) {
            if (str_starts_with($packed, str_repeat("\0", 10) . "\xff\xff")) {
                return (string)inet_ntop(substr($packed, 12));
            }

            return bin2hex(substr($packed, 0, 8)) . '::/64';
        }

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'unknown';
    }
}
