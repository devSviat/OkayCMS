<?php

namespace Security;

use PHPUnit\Framework\TestCase;

class AdminAuthTemplateEscapingTest extends TestCase
{
    public function testPreAuthTemplateEscapesTheHostHeader()
    {
        $template = $this->template();

        $this->assertStringContainsString('{$smarty.server.HTTP_HOST|escape}', $template);
        $this->assertStringNotContainsString('{$smarty.server.HTTP_HOST}', $template);
    }

    public function testSubmittedLoginIsEscapedWhenEchoedBack()
    {
        $template = $this->template();

        $this->assertStringNotContainsString('value="{$login}"', $template);
        $this->assertStringContainsString('value="{$login|escape}"', $template);
    }

    public function testNoUnescapedServerOrRequestValueRemains()
    {
        $template = $this->template();

        preg_match_all('/\{\$(smarty\.(server|get|post|request)\.[A-Za-z_]+|login|recovery_login|recovery_code)\}/', $template, $m);

        $this->assertSame([], $m[0], 'unescaped output in a pre-auth template');
    }

    private function template()
    {
        $template = file_get_contents(dirname(__DIR__, 2) . '/backend/design/html/auth.tpl');
        $this->assertIsString($template);

        return $template;
    }
}
