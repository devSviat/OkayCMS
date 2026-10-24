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
        $session = strpos($auth, '$managerSessions->signIn($manager)', (int)$verify);

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

    /**
     * З увімкненою 2FA пароль не дає входу: спершу перевірка 2FA, і лише без
     * неї — signIn(). Після листа відновлення код перевіряється ще до зміни
     * пароля й відкликання сесій: сама пошта не має забирати власнику пароль.
     */
    public function testTwoFactorIsCheckedBeforeSignInOnBothPaths(): void
    {
        $auth = $this->source('backend/Controllers/AuthAdmin.php');

        $recoveryStart = strpos($auth, '$manager = $managerToRecovery;');
        $recoveryVerify = strpos($auth, '$twoFactor->verify((int)$manager->id, $this->request->post(\'totp_code\'))', (int)$recoveryStart);
        $recoveryRefused = strpos($auth, 'return;', (int)$recoveryVerify);
        $this->assertNotFalse($recoveryStart);
        $this->assertNotFalse($recoveryVerify);
        foreach ([
            'зміна пароля' => "\$managersEntity->update((int)\$manager->id, ['password' => \$new_password]);",
            'відкликання сесій' => '$managerSessions->revokeAll((int)$manager->id, (int)$manager->id);',
            'вхід' => '$managerSessions->signIn($manager, $codeResult !== null)',
        ] as $what => $call) {
            $at = strpos($auth, $call, (int)$recoveryStart);
            $this->assertNotFalse($at, $what);
            $this->assertLessThan($at, $recoveryRefused, 'відновлення з листа: хибний код повертає до «' . $what . '»');
        }

        $passwordOk = strpos($auth, '$loginGuard->passwordSucceeded($login, $clientIp);');
        $passwordCheck = strpos($auth, '$twoFactor->isEnabled((int)$manager->id)', (int)$passwordOk);
        $passwordSignIn = strpos($auth, '$managerSessions->signIn($manager)', (int)$passwordOk);
        $this->assertNotFalse($passwordCheck);
        $this->assertLessThan($passwordSignIn, $passwordCheck, 'вхід паролем');

        // Гілка 2FA закінчується редіректом на код, а не падає до signIn().
        $this->assertSame(1, substr_count($auth, '$twoFactor->startChallenge('));
        $this->assertMatchesRegularExpression('~startChallenge\([^;]*\);\s*(unset\(\$_SESSION\[\'before_auth_url\'\]\);\s*)?\$response->redirectTo\(~s', $auth);
    }

    /** 2FA вмикають, коли пароль міг витекти: сесії, відкриті лише паролем, закриваються. */
    public function testEnablingTwoFactorEndsOtherSessions(): void
    {
        $admin = $this->source('backend/Controllers/ManagerAdmin.php');
        $confirm = strpos($admin, "case 'totp_confirm':");
        $next = strpos($admin, 'case ', (int)$confirm + 1);
        $this->assertNotFalse($confirm);

        $this->assertStringContainsString('$managerSessions->revokeOthers($viewerId, $viewerId);', substr($admin, $confirm, $next - $confirm));
    }

    /** Секрет і коди не переживають свого менеджера. */
    public function testDeletingAManagerDropsItsTwoFactor(): void
    {
        $this->assertMatchesRegularExpression(
            '~\$managersEntity->delete\(\[\$id\]\);.*?\$twoFactor->disable\(\$id, \$viewerId, \'manager_deleted\'\);~s',
            $this->source('backend/Controllers/ManagersAdmin.php')
        );
    }

    /** Крок коду пускає лише через signIn() з mfa = true і лише того самого менеджера. */
    public function testCodeStepSignsInTheSameManagerWithMfa(): void
    {
        $auth = $this->source('backend/Controllers/AuthAdmin.php');

        $this->assertStringContainsString("(string)\$manager->login !== \$challenge['login']", $auth);
        $this->assertStringContainsString('$managerSessions->signIn($manager, true)', $auth);
        $this->assertStringNotContainsString("\$_SESSION['admin'] =", $auth);
    }

    /**
     * Enter у полі коду відправляє форму першою її кнопкою. Скасування мусить
     * жити в окремій формі, інакше Enter скасовує вхід замість відправити код.
     */
    public function testEnterInTheCodeFieldSubmitsTheCode(): void
    {
        $tpl = $this->source('backend/design/html/auth.tpl');
        $start = strpos($tpl, '{elseif $two_factor_step}');
        $end = strpos($tpl, '</form>', (int)$start);
        $this->assertNotFalse($start);
        $step = substr($tpl, (int)$start, (int)$end - (int)$start);

        $this->assertStringNotContainsString('name="cancel_2fa"', $step, 'кнопка скасування — частина форми коду');
        $this->assertStringContainsString('form="fn_cancel_2fa"', $step);
        $this->assertStringContainsString('id="fn_cancel_2fa"', $tpl);
    }

    /** Спрацювання ліміту пароля потрапляє в журнал (раз на вікно — це робить сам guard). */
    public function testPasswordLimitIsLogged(): void
    {
        $auth = $this->source('backend/Controllers/AuthAdmin.php');
        $this->assertMatchesRegularExpression(
            '~if \(!\$loginGuard->allowPasswordAttempt\(\$login, \$clientIp\)\) \{[^}]*\$loginGuard->recordLimit\(~s',
            $auth
        );
        $this->assertStringContainsString('$this->loginGuard->recordLimit(', $this->source('Okay/Core/Security/ManagerReauth.php'));
    }

    /** Новий пароль із листа — подія в журналі власника, одразу після запису пароля. */
    public function testPasswordFromTheEmailIsLogged(): void
    {
        $this->assertMatchesRegularExpression(
            "~\\\$managersEntity->update\(\(int\)\\\$manager->id, \['password' => \\\$new_password\]\);\s*\\\$authLog->record\(ManagerAuthLog::PASSWORD_CHANGED~",
            $this->source('backend/Controllers/AuthAdmin.php')
        );
    }

    /**
     * Журнал менеджера видно рівно тим, хто бачить його сесії: і контролер не
     * віддає рядків, і шаблон не показує розділу без цього права.
     */
    public function testForeignLogIsShownOnlyWithTheSessionsRight(): void
    {
        $this->assertStringContainsString(
            "\$this->design->assign('auth_log', \$canSeeSessions ? \$this->authLog->of((int)\$target->id) : []);",
            $this->source('backend/Controllers/ManagerAdmin.php')
        );
        $this->assertMatchesRegularExpression(
            "~\{if \\\$can_see_sessions\}\s*<section class=\"settings_section\">\s*<div class=\"settings_section__about\">\s*<h2 class=\"settings_section__title\">\{\\\$btr->manager_log_heading~",
            $this->source('backend/design/html/manager.tpl')
        );
    }
}
