<?php


namespace Okay\Admin\Controllers;


use Okay\Core\Response;
use Okay\Core\Managers;
use Okay\Core\Notify;
use Okay\Core\Security\AdminRecoveryToken;
use Okay\Core\Security\SessionNames;
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
        AdminRecoveryToken $recoveryToken
    ) {
        /*Восстановление пароля администратора*/
        $recoveryEmail = $this->request->get('recovery_email');
        if ($this->request->get("ajax_recovery")) {
            $result = new \stdClass();
            if (!$validator->isEmail($recoveryEmail, true)) {
                $result->error = 'wrong_email';
            } else {
                $managerToRecovery = $managersEntity->findOne(['email' => $recoveryEmail]);

                if (!empty($managerToRecovery)) {
                    // Токен підписаний поточним хешем пароля менеджера, тому
                    // стає недійсним одразу після зміни пароля.
                    $code = $recoveryToken->create(
                        (int)$managerToRecovery->id,
                        (string)$managerToRecovery->password
                    );
                    $notify->emailPasswordRecoveryAdmin($managerToRecovery->email, $code);
                }

                // Відповідь однакова незалежно від того, чи існує такий
                // менеджер: інакше форма працює як оракул для перелічення
                // адміністраторів сайту.
                $result->send = true;
            }
            $this->response->setContent(json_encode($result), RESPONSE_JSON);
            $this->response->sendContent();
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
                    $managersEntity->update((int)$manager->id, [
                        'password' => $new_password,
                        'cnt_try'  => 0,
                        'last_try' => null,
                    ]);

                    SessionNames::regenerate();
                    $_SESSION['admin'] = $manager->login;

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
            $login = $this->request->post('login');
            $pass = $this->request->post('password');
            $manager = $managersEntity->get((string)$login);
            
            if ($manager) {
                /*Подсчитываем количество неправильны попыток входа*/
                $limit = 10;
                $now = date('Y-m-d');
                $last = (isset($manager->last_try) ? $manager->last_try : $now);
                if ($last != $now) {
                    $last = $now;
                    $manager->cnt_try = 1;
                } else {
                    $manager->cnt_try++;
                }

                if ($manager->cnt_try > $limit) {
                    $this->design->assign('error_message', 'limit_try');
                } elseif ($managers->checkPassword($pass, $manager->password)) {
                    /*Входим в админку*/
                    SessionNames::regenerate();
                    $_SESSION['admin'] = $manager->login;

                    // Старий формат хеша (APR1/MD5) замінюємо на актуальний.
                    // ManagersEntity::update() хешує пароль сам, тому
                    // передаємо його у відкритому вигляді.
                    if ($managers->needsPasswordRehash($manager->password)) {
                        $managersEntity->update((int)$manager->id, ['password' => $pass]);
                    }

                    $managersEntity->update((int)$manager->id, ['cnt_try'=>0, 'last_try'=>null]);
                    $managersEntity->updateLastActivityDate($manager->id);
                    $loginRedirectResource = (!empty($_SESSION['before_auth_url']) ? $_SESSION['before_auth_url'] : $this->request->getBasePathWithDomain() . '/backend/index.php');
                    unset($_SESSION['before_auth_url']);

                    $allManagers = $managersEntity->order('id ASC')->find();
                    $firstManager = reset($allManagers);

                    if ($lessonsEntity->count(['not_done' => 1]) > 0 && $firstManager->id === $manager->id) {
                        $response->redirectTo($this->request->getRootUrl() . '/backend/index.php?controller=LearningAdmin');
                    }
                    $response->redirectTo($loginRedirectResource);
                } else {
                    /*неверный пароль менеджера*/
                    $this->design->assign('login', $login);
                    $this->design->assign('error_message', 'auth_wrong');
                    $this->design->assign('limit_cnt', $limit-$manager->cnt_try);
                    $managersEntity->update((int)$manager->id, ['cnt_try'=>$manager->cnt_try, 'last_try'=>$last]);
                }
            } else {
                /*менеджер не найден*/
                $this->design->assign('login', $login);
                $this->design->assign('error_message', 'auth_wrong');
            }
        }
        $this->response->setContent($this->design->fetch('auth.tpl'));
    }

}
