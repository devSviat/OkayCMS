<?php

namespace Security;

use PHPUnit\Framework\TestCase;

class RecaptchaFailClosedTest extends TestCase
{
    public function testInvalidSecretNoLongerPassesTheCheck()
    {
        $source = $this->source();

        $marker = strpos($source, 'invalid-input-secret');
        $this->assertIsInt($marker);

        $branch = substr($source, $marker, 600);
        $this->assertStringNotContainsString('return true;', $branch);
        $this->assertStringContainsString('return false;', $branch);
    }

    public function testMisconfigurationIsLogged()
    {
        $source = $this->source();

        $this->assertStringContainsString('LoggerInterface', $source);
        $this->assertStringContainsString('$this->logger', $source);
    }

    public function testMissingSuccessKeyIsTreatedAsFailure()
    {
        $source = $this->source();

        $this->assertStringNotContainsString("\$response['success'] == false", $source);
        $this->assertStringContainsString("empty(\$response['success'])", $source);
    }

    public function testUnreadableApiResponseIsTreatedAsFailure()
    {
        $source = $this->source();

        $this->assertStringContainsString('is_array($response)', $source);
    }

    public function testErrorCodesAreCheckedAcrossTheWholeList()
    {
        $source = $this->source();

        // reset() бачить лише перший код, а API повертає масив
        $this->assertStringNotContainsString("reset(\$response['error-codes'])", $source);
        $this->assertStringContainsString('in_array(', $source);
    }

    private function source()
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/Okay/Core/Recaptcha.php');
        $this->assertIsString($source);

        return $source;
    }
}
