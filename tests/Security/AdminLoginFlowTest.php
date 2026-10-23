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

        foreach (['->allowRecoveryEmail($clientIp)', '->allowRecoveryEmailTo('] as $limit) {
            $this->assertNotFalse(strpos($auth, $limit), $limit);
            $this->assertLessThan($send, strpos($auth, $limit), $limit);
        }
    }

    /**
     * Лист іде після відповіді й після звільнення сесії: інакше SMTP у часі
     * відповіді — або в очікуванні паралельного запиту з тією самою кукою на
     * блокування сесії — видавав, що менеджер є. Ліміт на скриньку — теж після.
     */
    public function testRecoveryEmailIsSentAfterTheResponseAndTheSessionRelease(): void
    {
        $auth = $this->source('backend/Controllers/AuthAdmin.php');
        $respond = strpos($auth, '$this->response->sendContent();');
        $release = strpos($auth, 'session_write_close();');
        $finish = strpos($auth, 'fastcgi_finish_request();');
        $recipientLimit = strpos($auth, '->allowRecoveryEmailTo((string)$managerToRecovery->email)');
        $send = strpos($auth, '->emailPasswordRecoveryAdmin(');

        foreach ([$release, $finish, $recipientLimit] as $position) {
            $this->assertNotFalse($position);
        }
        $this->assertLessThan($release, $respond);
        $this->assertLessThan($finish, $release);
        $this->assertLessThan($recipientLimit, $finish);
        $this->assertLessThan($send, $recipientLimit);
    }

    /** Успіх обнуляє лічильник облікового запису й повертає спробу адресі. */
    public function testSuccessIsReportedToTheGuard(): void
    {
        $auth = $this->source('backend/Controllers/AuthAdmin.php');
        $verify = strpos($auth, '->verifyPassword(');
        $succeeded = strpos($auth, '$loginGuard->passwordSucceeded($login, $clientIp);');
        $session = strpos($auth, "\$_SESSION['admin'] = \$manager->login;", (int)$verify);

        $this->assertNotFalse($succeeded);
        $this->assertLessThan($succeeded, $verify);
        $this->assertLessThan($session, $succeeded);
    }

    /** Масив у POST (`login[]=`) давав Warning у лог. */
    public function testOnlyStringCredentialsAreAccepted(): void
    {
        $auth = $this->source('backend/Controllers/AuthAdmin.php');

        $this->assertStringContainsString('$login = is_string($login) ? $login : \'\';', $auth);
        $this->assertStringContainsString('$pass = is_string($pass) ? $pass : \'\';', $auth);
    }
}
