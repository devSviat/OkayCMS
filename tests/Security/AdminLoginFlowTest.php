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

    public function testRecoveryEmailIsLimited(): void
    {
        $auth = $this->source('backend/Controllers/AuthAdmin.php');
        $limit = strpos($auth, '->allowRecoveryEmail(');

        $this->assertNotFalse($limit);
        $this->assertLessThan(strpos($auth, '->emailPasswordRecoveryAdmin('), $limit);
    }
}
