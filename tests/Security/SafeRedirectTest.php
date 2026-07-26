<?php

namespace Security;

use Okay\Core\Security\SafeRedirect;
use PHPUnit\Framework\TestCase;

class SafeRedirectTest extends TestCase
{
    const BASE = 'http://okaycms.loc';

    /**
     * @dataProvider allowedProvider
     */
    public function testSameOriginUrlsAreAllowed($url)
    {
        $this->assertTrue(SafeRedirect::isSameOrigin($url, self::BASE), var_export($url, true));
    }

    public function allowedProvider()
    {
        return [
            'root'               => ['/'],
            'path'               => ['/catalog/shoes'],
            'path with query'    => ['/catalog?page=2'],
            'path with fragment' => ['/catalog#top'],
            'absolute same host' => ['http://okaycms.loc/catalog'],
            'https same host'    => ['https://okaycms.loc/catalog'],
            'host case differs'  => ['http://OKAYCMS.loc/catalog'],
        ];
    }

    /**
     * @dataProvider rejectedProvider
     */
    public function testForeignAndMalformedUrlsAreRejected($url)
    {
        $this->assertFalse(SafeRedirect::isSameOrigin($url, self::BASE), var_export($url, true));
    }

    public function rejectedProvider()
    {
        return [
            'null'                 => [null],
            'empty'                => [''],
            'not a string'         => [123],
            'protocol relative'    => ['//evil.com/x'],
            'encoded protocol rel' => ['%2f%2fevil.com/x'],
            'double encoded'       => ['%252f%252fevil.com/x'],
            'backslash'            => ['/\\evil.com'],
            'backslash pair'       => ['\\\\evil.com'],
            'foreign host'         => ['http://evil.com/x'],
            'foreign host https'   => ['https://evil.com/x'],
            'javascript scheme'    => ['javascript:alert(1)'],
            'data scheme'          => ['data:text/html,<script>alert(1)</script>'],
            'newline injection'    => ["/catalog\r\nSet-Cookie: a=b"],
            'nul byte'             => ["/catalog\0"],
            'userinfo trick'       => ['http://okaycms.loc@evil.com/'],
            'subdomain trick'      => ['http://okaycms.loc.evil.com/'],
            'relative no slash'    => ['catalog'],
        ];
    }

    public function testBrokenBaseUrlRejectsEverything()
    {
        $this->assertFalse(SafeRedirect::isSameOrigin('http://okaycms.loc/x', 'not a url'));
    }

    public function testPrgHelperValidatesTheTarget()
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/Okay/Helpers/MainHelper.php');
        $this->assertIsString($source);

        $this->assertStringContainsString('SafeRedirect::isSameOrigin(', $source);

        $guard = strpos($source, 'SafeRedirect::isSameOrigin(');
        $redirect = strpos($source, 'Response::redirectTo($prgSeoHide)');

        $this->assertIsInt($guard);
        $this->assertIsInt($redirect);
        $this->assertLessThan($redirect, $guard);
    }
}
