<?php

namespace Security;

use Okay\Core\Security\SecurityHeaders;
use PHPUnit\Framework\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function testDefaultsCoverFramingSniffingAndReferrer()
    {
        $headers = SecurityHeaders::defaults();

        $this->assertContains('X-Frame-Options: SAMEORIGIN', $headers);
        $this->assertContains('X-Content-Type-Options: nosniff', $headers);
        $this->assertContains('Referrer-Policy: strict-origin-when-cross-origin', $headers);
    }

    public function testDefaultsAreHeaderLines()
    {
        foreach (SecurityHeaders::defaults() as $header) {
            $this->assertIsString($header);
            $this->assertStringContainsString(': ', $header);
            $this->assertStringNotContainsString("\n", $header);
            $this->assertStringNotContainsString("\r", $header);
        }
    }

    public function testResponseNoLongerAdvertisesTheVersion()
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/Okay/Core/Response.php');
        $this->assertIsString($source);

        $this->assertStringNotContainsString("'X-Powered-CMS: OkayCMS ' . \$version", $source);
        $this->assertStringContainsString('SecurityHeaders::defaults()', $source);
    }
}
