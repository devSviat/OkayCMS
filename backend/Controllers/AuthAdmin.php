<?php


namespace Okay\Admin\Controllers;


use Okay\Core\Response;
use Okay\Core\Managers;
use Okay\Core\Notify;
use Okay\Core\Security\AdminLoginGuard;
use Okay\Core\Security\AdminRecoveryToken;
use Okay\Core\Security\ManagerSessions;
use Okay\Core\Security\ManagerTwoFactor;
use Okay\Core\Validator;
use Okay\Entities\LessonsEntity;
use Okay\Entities\ManagersEntity;

class AuthAdmin extends IndexAdmin
{

    public function fetch(
        Managers $managers,
        ManagersEntity $managersEntity,
        LessonsEntity $lessonsEntity,
        Notify $notify,
        Response $response,
        Validator $validator,
        AdminRecoveryToken $recoveryToken,
        AdminLoginGuard $loginGuard,
        ManagerSessions $managerSessions,
        ManagerTwoFactor $twoFactor
    ) {
        $clientIp = (string)($_SERVER['REMOTE_ADDR'] ?? '');

        // Друга сходинка: пароль уже прийнято, чекаємо код. Мітки admin
        // немає, тож жоден вхід адмінки ще не пускає.
        if ($this->handleTwoFactorStep($twoFactor, $managersEntity, $managerSessions)) {
            return;
        }

        /*Восстановление пароля администратора*/
        $recoveryEmail = $this->request->get('recovery_email');
        $recoveryEmail = is_string($recoveryEmail) ? $recoveryEmail : '';
        if ($this->request->get("ajax_recovery")) {
            $result = new \stdClass();
            $managerToRecovery = null;
            if (!$validator->isEmail($recoveryEmail, true)) {
                $result->error = 'wrong_email';
            } else {
                // Понад ліміт адреси відповідь та сама, що й на успіх: окрема
                // помилка підказала б, що ліміт рахує саме листи.
                if ($loginGuard->allowRecoveryEmail($clientIp)) {
                    $found = $managersEntity->findOne(['email' => $recoveryEmail]);
                    $managerToRecovery = is_object($found) ? $found : null;
                }

                // Відповідь однакова незалежно від того, чи існує такий
                // менеджер: інакше форма працює як оракул для перелічення
                // адміністраторів сайту.
                $result->send = true;
            }
            $this->response->setContent(json_encode($result), RESPONSE_JSON);
            $this->response->sendContent();

            // Далі робота залежить від того, чи є такий менеджер, тож вона йде
            // після відповіді: SMTP займає десятки мілісекунд. Сесію звільняємо
            // теж: інакше паралельний запит з тією самою кукою чекав би на
            // блокування до кінця листа й видавав те саме.
            session_write_close();
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            } elseif (function_exists('litespeed_finish_request')) {
                litespeed_finish_request();
            }

            if ($managerToRecovery !== null && $loginGuard->allowRecoveryEmailTo((string)$managerToRecovery->email)) {
                // Токен підписаний поточним хешем пароля менеджера, тому
                // стає недійсним одразу після зміни пароля.
                $code = $recoveryToken->create(
                    (int)$managerToRecovery->id,
                    (string)$managerToRecovery->password
                );
                $notify->emailPasswordRecoveryAdmin($managerToRecovery->email, $code);
            }
            exit;
        }

        // Код приходить у GET за посиланням з листа й переноситься через POST
        // прихованим полем, щоб надсилання форми лишилось у режимі відновлення.
        $code = (string)($this->request->get('code') ?: $this->request->post('code'));
        $managerIdFromCode = $code === '' ? null : $recoveryToken->unverifiedManagerId($code);
        $managerToRecovery = $managerIdFromCode === null ? null : $managersEntity->get($managerIdFromCode);
        $recoveryIsValid = !empty($managerToRecovery)
            && $recoveryToken->managerId($code, (string)$managerToRecovery->password) !== null;

        if ($recoveryIsValid) {
            $this->design->assign('recovery_mod', true);
            $this->design->assign('recovery_code', $code);

            if ($this->request->method('post')){
                $new_password = $this->request->post('new_password');
                $new_password_check = $this->request->post('new_password_check');

                if (trim($new_password) === '') {
                    $this->design->assign('error_message', 'password_empty');
                } elseif ($new_password !== $new_password_check) {
                    $this->design->assign('error_message', 'password_wrong');
                } else {
                    // Менеджер береться з токена, а не з POST: інакше власник
                    // будь-якого валідного коду міг змінити пароль кому завгодно.
                    $manager = $managerToRecovery;

                    // ManagersEntity::update() хешує пароль сам.
                    $managersEntity->update((int)$manager->id, ['password' => $new_password]);

                    // Новий пароль закриває всі наявні входи: відновлення
                    // найчастіше роблять саме тоді, коли пароль міг витекти.
                    $managerSessions->revokeAll((int)$manager->id, (int)$manager->id);

                    // Доступ до пошти не має обходити 2FA: після нового пароля — код.
                    if ($twoFactor->isEnabled((int)$manager->id)) {
                        $twoFactor->startChallenge($manager, $this->request->getRootUrl() . '/backend/index.php');
                        $response->redirectTo($this->request->getRootUrl() . '/backend/index.php?controller=AuthAdmin');
                    }

                    if (!$managerSessions->signIn($manager)) {
                        $response->redirectTo($this->request->getRootUrl() . '/backend/index.php?controller=AuthAdmin');
                    }

                    $allManagers = $managersEntity->order('id ASC')->find();
                    $firstManager = reset($allManagers);

                    if ($lessonsEntity->count(['not_done' => 1]) > 0 && $firstManager->id === $manager->id) {
                        $response->redirectTo($this->request->getRootUrl() . '/backend/index.php?controller=LearningAdmin');
                    }
                    $response->redirectTo($this->request->getRootUrl() . '/backend/index.php');
                }
            }

        } elseif ($this->request->method('post')) {
            /*Авторизация в админ.панель*/
            // Масив у POST (`login[]=`) інакше давав Warning у лог і «Array» у формі.
            $login = $this->request->post('login');
            $login = is_string($login) ? $login : '';
            $pass = $this->request->post('password');
            $pass = is_string($pass) ? $pass : '';

            if (!$loginGuard->allowPasswordAttempt($login, $clientIp)) {
                $this->design->assign('login', $login);
                $this->design->assign('error_message', 'limit_try');
            } else {
                $manager = $login === '' ? null : ($managersEntity->get($login) ?: null);

                // Відповідь на хибну пару однакова для існуючого й вигаданого
                // логіна, і за текстом, і за часом (AdminLoginGuard::verifyPassword).
                if (!$loginGuard->verifyPassword($manager, $pass)) {
                    $this->design->assign('login', $login);
                    $this->design->assign('error_message', 'auth_wrong');
                } else {
                    $loginGuard->passwordSucceeded($login, $clientIp);

                    // Старий формат хеша (APR1/MD5) замінюємо на актуальний ще
                    // до коду: пароль уже перевірено.
                    if ($managers->needsPasswordRehash($manager->password)) {
                        $managersEntity->update((int)$manager->id, ['password' => $pass]);
                    }

                    if ($twoFactor->isEnabled((int)$manager->id)) {
                        $twoFactor->startChallenge(
                            $manager,
                            !empty($_SESSION['before_auth_url']) ? (string)$_SESSION['before_auth_url'] : $this->request->getRootUrl() . '/backend/index.php'
                        );
                        unset($_SESSION['before_auth_url']);
                        $response->redirectTo($this->request->getRootUrl() . '/backend/index.php?controller=AuthAdmin');
                    }

                    /*Входим в админку*/
                    if (!$managerSessions->signIn($manager)) {
                        $this->design->assign('login', $login);
                        $this->design->assign('error_message', 'session_failed');
                        $this->response->setContent($this->design->fetch('auth.tpl'));
                        return;
                    }

                    $managersEntity->updateLastActivityDate($manager->id);
                    $loginRedirectResource = (!empty($_SESSION['before_auth_url']) ? $_SESSION['before_auth_url'] : $this->request->getRootUrl() . '/backend/index.php');
                    unset($_SESSION['before_auth_url']);

                    $allManagers = $managersEntity->order('id ASC')->find();
                    $firstManager = reset($allManagers);

                    if ($lessonsEntity->count(['not_done' => 1]) > 0 && $firstManager->id === $manager->id) {
                        $response->redirectTo($this->request->getRootUrl() . '/backend/index.php?controller=LearningAdmin');
                    }
                    $response->redirectTo($loginRedirectResource);
                }
            }
        }
        $this->response->setContent($this->design->fetch('auth.tpl'));
    }


    /**
     * Сторінка коду після пароля. true — запит оброблено тут (форма коду,
     * вхід або повернення до пароля).
     */
    private function handleTwoFactorStep(
        ManagerTwoFactor $twoFactor,
        ManagersEntity $managersEntity,
        ManagerSessions $managerSessions
    ): bool {
        $authUrl = $this->request->getRootUrl() . '/backend/index.php?controller=AuthAdmin';

        if ($this->request->method('post') && $this->request->post('cancel_2fa')) {
            $twoFactor->clearChallenge();
            $this->response->redirectTo($authUrl);
        }

        $challenge = $twoFactor->challenge();
        if ($challenge === null) {
            return false;
        }

        $error = null;
        if ($this->request->method('post') && $this->request->post('totp_code') !== null) {
            $result = $twoFactor->verify($challenge['manager_id'], $this->request->post('totp_code'));

            if ($result === ManagerTwoFactor::OK || $result === ManagerTwoFactor::OK_RECOVERY) {
                $manager = $managersEntity->get($challenge['manager_id']);
                $twoFactor->clearChallenge();

                // Менеджера могли перейменувати чи видалити, поки він вводив код.
                if (!is_object($manager) || (string)$manager->login !== $challenge['login'] || !$managerSessions->signIn($manager, true)) {
                    $this->response->redirectTo($authUrl);
                }

                $managersEntity->updateLastActivityDate($manager->id);
                if ($result === ManagerTwoFactor::OK_RECOVERY) {
                    $_SESSION['recovery_code_used'] = true;
                }
                // Адреса з сесії, але веде лише в межах сайту.
                $redirect = (string)$challenge['redirect'];
                $root = $this->request->getRootUrl() . '/';
                $this->response->redirectTo(str_starts_with($redirect, $root) ? $redirect : $root . 'backend/index.php');
            }

            if ($result === ManagerTwoFactor::LIMIT) {
                // Понад ліміт кодів — знову пароль: інакше шість цифр можна
                // перебирати, маючи лише пароль.
                $twoFactor->clearChallenge();
                $this->design->assign('error_message', 'limit_try');
                $this->response->setContent($this->design->fetch('auth.tpl'));

                return true;
            }

            $error = $result === ManagerTwoFactor::KEY_UNAVAILABLE ? 'key_unavailable' : 'code_wrong';
        }

        $this->design->assign('two_factor_step', true);
        $this->design->assign('two_factor_login', $challenge['login']);
        $this->design->assign('error_message', $error);
        $this->response->setContent($this->design->fetch('auth.tpl'));

        return true;
    }
}
