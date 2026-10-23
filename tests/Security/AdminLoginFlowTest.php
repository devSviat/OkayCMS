<?php

namespace Security;

use PHPUnit\Framework\TestCase;

/**
 * Правила входу живуть в AdminLoginGuard; тут — що AuthAdmin ними справді
 * користується і не повертає старих витоків.
 */
class AdminLoginFlowTest extends TestCase
{
    private function source(string $path): string
    {
        return (string)file_get_contents(dirname(__DIR__, 2) . '/' . $path);
    }

    /** Ліміт займається до перевірки пароля, інакше перебір іде без меж. */
    public function testAttemptIsTakenBeforeThePasswordIsChecked(): void
    {
        $auth = $this->source('backend/Controllers/AuthAdmin.php');

        $attempt = strpos($auth, '->allowPasswordAttempt(');
        $verify = strpos($auth, '->verifyPassword(');
        $this->assertNotFalse($attempt);
        $this->assertNotFalse($verify);
        $this->assertLessThan($verify, $attempt);
        $this->assertStringNotContainsString('->checkPassword(', $auth, 'пароль перевіряється лише через AdminLoginGuard');
    }

    /** «Залишилося N спроб» друкувалось лише для існуючого логіна. */
    public function testRemainingAttemptsAreNotPrinted(): void
    {
        $this->assertStringNotContainsString('limit_cnt', $this->source('backend/Controllers/AuthAdmin.php'));
        $this->assertStringNotContainsString('limit_cnt', $this->source('backend/design/html/auth.tpl'));
    }

    /**
     * Без збереженої адреси після входу ведемо в корінь адмінки. Поточна адреса
     * тут — сторінка входу, і дописаний до неї шлях давав
     * «?controller=AuthAdmin/backend/index.php».
     */
    public function testFallbackAfterLoginIsTheAdminRoot(): void
    {
        $this->assertStringContainsString(
            "\$this->request->getRootUrl() . '/backend/index.php');",
            $this->source('backend/Controllers/AuthAdmin.php')
        );
    }

    public function testRecoveryEmailIsLimited(): void
    {
        $auth = $this->source('backend/Controllers/AuthAdmin.php');
        $send = strpos($auth, '->emailPasswordRecoveryAdmin(');

        foreach (['->allowRecoveryEmail(', '->allowRecoveryEmailTo('] as $limit) {
            $this->assertNotFalse(strpos($auth, $limit), $limit);
            $this->assertLessThan($send, strpos($auth, $limit), $limit);
        }
    }

    /** Лист іде після відповіді: інакше SMTP у часі відповіді видавав, що менеджер є. */
    public function testRecoveryEmailIsSentAfterTheResponse(): void
    {
        $auth = $this->source('backend/Controllers/AuthAdmin.php');
        $respond = strpos($auth, '$this->response->sendContent();');
        $finish = strpos($auth, 'fastcgi_finish_request();');
        $send = strpos($auth, '->emailPasswordRecoveryAdmin(');

        $this->assertNotFalse($finish);
        $this->assertLessThan($finish, $respond);
        $this->assertLessThan($send, $finish);
    }

    /** Масив у POST (`login[]=`) давав Warning у лог. */
    public function testOnlyStringCredentialsAreAccepted(): void
    {
        $auth = $this->source('backend/Controllers/AuthAdmin.php');

        $this->assertStringContainsString('$login = is_string($login) ? $login : \'\';', $auth);
        $this->assertStringContainsString('$pass = is_string($pass) ? $pass : \'\';', $auth);
    }
}
