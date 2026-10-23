<?php

namespace Security;

use Okay\Core\Security\ClientIp;
use PHPUnit\Framework\TestCase;

class ClientIpTest extends TestCase
{
    public function testIpv4IsKeptAsIs(): void
    {
        $this->assertSame('203.0.113.7', ClientIp::bucket('203.0.113.7'));
    }

    /** Мережа /64 — один ключ: інакше кожна з мільярдів адрес давала б свій ліміт. */
    public function testIpv6IsBucketedByItsSlash64(): void
    {
        $this->assertSame(ClientIp::bucket('2001:db8:1:2::1'), ClientIp::bucket('2001:db8:1:2:ffff:ffff:ffff:ffff'));
        $this->assertNotSame(ClientIp::bucket('2001:db8:1:2::1'), ClientIp::bucket('2001:db8:1:3::1'));
    }

    public function testGarbageIsOneSharedBucket(): void
    {
        $this->assertSame('unknown', ClientIp::bucket(''));
        $this->assertSame('unknown', ClientIp::bucket('../../etc'));
    }
}
