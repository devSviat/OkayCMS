<?php

namespace Security;

use PHPUnit\Framework\TestCase;

class CustomerRecoveryFlowTest extends TestCase
{
    public function testRecoveryLinkDoesNotLogTheCustomerIn()
    {
        $source = $this->source();

        $this->assertStringContainsString('RecoveryToken', $source);
        $this->assertStringNotContainsString("\$_SESSION['user_id'] = \$user->id;", $source);
        $this->assertStringNotContainsString("find(['remind_code'=>\$code", $source);
    }

    public function testDigestIsStoredInsteadOfTheRawCode()
    {
        $source = $this->source();

        $this->assertStringContainsString('->digest(', $source);
        $this->assertStringNotContainsString("md5(uniqid(\$this->config->salt, true))", $source);
    }

    public function testResetRequiresNonEmptyMatchingPassword()
    {
        $source = $this->source();

        $this->assertStringContainsString("trim(\$newPassword) === ''", $source);
        $this->assertStringContainsString('$newPassword !== $newPasswordCheck', $source);
    }

    public function testTokenIsConsumedBeforeTheSessionIsElevated()
    {
        $source = $this->source();

        $consume = strpos($source, "'remind_code' => null");
        $login = strpos($source, "\$_SESSION['user_id'] = ");

        $this->assertIsInt($consume);
        $this->assertIsInt($login);
        $this->assertLessThan($login, $consume);
    }

    public function testSessionIsRegeneratedOnRecoveryLogin()
    {
        $source = $this->source();

        $regenerate = strpos($source, 'SessionNames::regenerate();');
        $login = strpos($source, "\$_SESSION['user_id'] = ");

        $this->assertIsInt($regenerate);
        $this->assertIsInt($login);
        $this->assertLessThan($login, $regenerate);
    }

    public function testRequestDoesNotEnumerateCustomerAccounts()
    {
        $source = $this->source();
        $template = $this->template();

        $this->assertStringContainsString("\$this->design->assign('email_sent', true);", $source);
        $this->assertStringNotContainsString("'error', 'user_not_found'", $source);
        $this->assertStringNotContainsString('user_not_found', $template);
    }

    public function testTemplateNeitherEchoesTheSubmittedEmailNorTheToken()
    {
        $template = $this->template();

        $this->assertStringNotContainsString('{$email|escape}', $template);
        $this->assertStringNotContainsString('$code', $template);
    }

    public function testTemplateHasTheResetState()
    {
        $template = $this->template();

        $this->assertStringContainsString('$recovery_mode', $template);
        $this->assertStringContainsString('name="new_password"', $template);
        $this->assertStringContainsString('name="new_password_check"', $template);
        $this->assertStringContainsString('name="reset_password"', $template);
        $this->assertStringContainsString('autocomplete="new-password"', $template);
    }

    /**
     * @dataProvider languageFileProvider
     */
    public function testNewLanguageKeysExistInEveryLanguage($file)
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/design/okay_shop/lang/' . $file);
        $this->assertIsString($source, $file);

        foreach ([
            'password_remind_letter_sent_generic',
            'password_remind_expired',
            'password_remind_password_empty',
            'password_remind_password_wrong',
            'password_remind_new_password',
            'password_remind_save',
        ] as $key) {
            $this->assertStringContainsString("\$lang['" . $key . "']", $source, $file . ': ' . $key);
        }
    }

    public function languageFileProvider()
    {
        return [
            'ru' => ['ru.php'],
            'en' => ['en.php'],
            'ua' => ['ua.php'],
        ];
    }

    private function source()
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/Okay/Controllers/UserController.php');
        $this->assertIsString($source);

        return $source;
    }

    private function template()
    {
        $template = file_get_contents(dirname(__DIR__, 2) . '/design/okay_shop/html/password_remind.tpl');
        $this->assertIsString($template);

        return $template;
    }
}
