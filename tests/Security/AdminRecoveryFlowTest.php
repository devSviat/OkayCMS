<?php

namespace Security;

use PHPUnit\Framework\TestCase;

class AdminRecoveryFlowTest extends TestCase
{
    public function testRecoveryIsBoundToTheTokenNotToPostedLogin()
    {
        $source = $this->source();

        $this->assertMatchesRegularExpression('~(?<![\w-])AdminRecoveryToken(?![\w-])~', $source);
        $this->assertStringNotContainsString("\$this->request->post('new_login')", $source);
        $this->assertStringNotContainsString("\$managersEntity->add(['login'", $source);
        $this->assertStringNotContainsString("\$_SESSION['admin_password_recovery_code']", $source);
    }

    public function testRecoveryDoesNotEnumerateAdminEmails()
    {
        $source = $this->source();

        $this->assertStringNotContainsString("\$result->error = 'not_admin_email';", $source);
        $this->assertStringContainsString('$result->send = true;', $source);
    }

    public function testEmptyPasswordIsRejectedBeforeLogin()
    {
        $source = $this->source();

        $this->assertStringContainsString("trim(\$new_password) === ''", $source);
        $this->assertStringContainsString("\$this->design->assign('error_message', 'password_empty');", $source);

        $guard = strpos($source, "trim(\$new_password) === ''");
        $login = strpos($source, "\$_SESSION['admin'] = \$manager->login;");

        $this->assertIsInt($guard);
        $this->assertIsInt($login);
        $this->assertLessThan($login, $guard);
    }

    public function testMismatchedConfirmationIsRejected()
    {
        $source = $this->source();

        $this->assertStringContainsString('$new_password !== $new_password_check', $source);
        $this->assertStringContainsString("\$this->design->assign('error_message', 'password_wrong');", $source);
    }

    public function testSessionIsRegeneratedOnRecoveryLogin()
    {
        $source = $this->source();

        $regenerate = strpos($source, 'SessionNames::regenerate();');
        $login = strpos($source, "\$_SESSION['admin'] = \$manager->login;");

        $this->assertIsInt($regenerate);
        $this->assertIsInt($login);
        $this->assertLessThan($login, $regenerate);
    }

    public function testRecoveryFormNoLongerAsksForLogin()
    {
        $template = $this->template();

        $this->assertStringNotContainsString('name="new_login"', $template);
    }

    public function testRecoveryFormCarriesTheCodeAndRendersItsErrors()
    {
        $template = $this->template();

        $this->assertStringContainsString('name="code"', $template);
        $this->assertStringContainsString("\$error_message == 'password_empty'", $template);
        $this->assertStringContainsString("\$error_message == 'password_wrong'", $template);
    }

    public function testRecoveryJsNoLongerRevealsWhetherTheEmailIsAnAdmin()
    {
        $template = $this->template();

        $this->assertStringNotContainsString('not_admin_email', $template);
    }

    private function source()
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/backend/Controllers/AuthAdmin.php');
        $this->assertIsString($source);

        return $source;
    }

    private function template()
    {
        $template = file_get_contents(dirname(__DIR__, 2) . '/backend/design/html/auth.tpl');
        $this->assertIsString($template);

        return $template;
    }
}
