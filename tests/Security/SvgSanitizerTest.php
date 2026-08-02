<?php

namespace Security;

use Okay\Core\Security\SvgSanitizer;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class SvgSanitizerTest extends TestCase
{
    public function testBenignShapesSurvive()
    {
        $result = (new SvgSanitizer())->sanitize(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10">'
            . '<path d="M0 0 L10 10" fill="#ff0000"/><circle cx="5" cy="5" r="4"/></svg>'
        );

        $this->assertIsString($result);
        $this->assertStringContainsString('<path', $result);
        $this->assertStringContainsString('d="M0 0 L10 10"', $result);
        $this->assertStringContainsString('<circle', $result);
        $this->assertStringContainsString('viewBox="0 0 10 10"', $result);
    }

    public function testScriptElementsAreRemoved()
    {
        $result = (new SvgSanitizer())->sanitize(
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><rect width="1" height="1"/></svg>'
        );

        $this->assertIsString($result);
        $this->assertStringNotContainsString('script', $result);
        $this->assertStringNotContainsString('alert', $result);
        $this->assertStringContainsString('<rect', $result);
    }

    public function testEventHandlerAttributesAreRemoved()
    {
        $result = (new SvgSanitizer())->sanitize(
            '<svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1" onload="alert(1)" onclick="alert(2)"/></svg>'
        );

        $this->assertIsString($result);
        $this->assertStringNotContainsString('onload', $result);
        $this->assertStringNotContainsString('onclick', $result);
        $this->assertStringNotContainsString('alert', $result);
    }

    public function testDangerousUrlSchemesAreRemoved()
    {
        $result = (new SvgSanitizer())->sanitize(
            '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">'
            . '<a xlink:href="javascript:alert(1)"><rect width="1" height="1"/></a></svg>'
        );

        $this->assertIsString($result);
        $this->assertStringNotContainsString('javascript:', $result);
    }

    public function testForeignObjectAndEmbeddedMarkupAreRemoved()
    {
        $result = (new SvgSanitizer())->sanitize(
            '<svg xmlns="http://www.w3.org/2000/svg"><foreignObject><b>x</b></foreignObject>'
            . '<rect width="1" height="1"/></svg>'
        );

        $this->assertIsString($result);
        $this->assertStringNotContainsString('foreignObject', $result);
        $this->assertStringContainsString('<rect', $result);
    }

    public function testDangerousStyleValuesAreRemoved()
    {
        $result = (new SvgSanitizer())->sanitize(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<rect width="1" height="1" style="fill:url(javascript:alert(1))"/></svg>'
        );

        $this->assertIsString($result);
        $this->assertStringNotContainsString('javascript:', $result);
    }

    public function testExternalEntitiesAreNotResolved()
    {
        $result = (new SvgSanitizer())->sanitize(
            '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>'
            . '<svg xmlns="http://www.w3.org/2000/svg"><text>&xxe;</text></svg>'
        );

        if ($result !== null) {
            $this->assertStringNotContainsString('root:', $result);
            $this->assertStringNotContainsString('/bin/', $result);
        } else {
            $this->assertNull($result);
        }
    }

    #[DataProvider('rejectedInputProvider')]
    public function testNonSvgInputIsRejected($input)
    {
        $this->assertNull((new SvgSanitizer())->sanitize($input));
    }

    public static function rejectedInputProvider()
    {
        return [
            'empty'      => [''],
            'whitespace' => ["  \n "],
            'plain text' => ['not an svg at all'],
            'html'       => ['<html><body>hi</body></html>'],
            'broken xml' => ['<svg><rect'],
        ];
    }

    public function testSanitizeFileRewritesInPlace()
    {
        $path = sys_get_temp_dir() . '/okay-svg-' . getmypid() . '.svg';
        file_put_contents(
            $path,
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><rect width="1" height="1"/></svg>'
        );

        $this->assertTrue((new SvgSanitizer())->sanitizeFile($path));
        $this->assertStringNotContainsString('script', file_get_contents($path));

        @unlink($path);
    }

    public function testSanitizeFileRejectsNonSvgAndLeavesItUntouched()
    {
        $path = sys_get_temp_dir() . '/okay-svg-bad-' . getmypid() . '.svg';
        file_put_contents($path, 'definitely not svg');

        $this->assertFalse((new SvgSanitizer())->sanitizeFile($path));
        $this->assertSame('definitely not svg', file_get_contents($path));

        @unlink($path);
    }
}
