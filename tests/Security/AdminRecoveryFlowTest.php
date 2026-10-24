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
        $login = strpos($source, '$managerSessions->signIn($manager)');

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

    /**
     * Вхід після відновлення — через ManagerSessions::signIn(), який міняє
     * ідентифікатор сесії (SessionNamesTest). Перед ним гасяться всі наявні
     * сесії менеджера: відновлюють пароль, коли старий міг витекти.
     */
    public function testRecoveryLoginRevokesOldSessionsAndSignsInThroughTheRegistry()
    {
        $source = $this->source();

        $update = strpos($source, "\$saved = \$managersEntity->update((int)\$manager->id, ['password' => \$new_password]);");
        $revoke = strpos($source, '$managerSessions->revokeAll((int)$manager->id, (int)$manager->id);');
        $login = strpos($source, '$managerSessions->signIn($manager)');

        $this->assertIsInt($update);
        $this->assertIsInt($revoke);
        $this->assertIsInt($login);
        $this->assertLessThan($revoke, $update);
        $this->assertLessThan($login, $revoke);
        $this->assertStringNotContainsString("\$_SESSION['admin'] =", $source, 'мітку адміна ставить лише signIn()');
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
