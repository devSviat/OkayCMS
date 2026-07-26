<?php


namespace Okay\Admin\Controllers;


use Okay\Core\Response;
use Okay\Core\Managers;
use Okay\Core\Notify;
use Okay\Core\Security\AdminRecoveryToken;
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
                    // Токен подписан текущим хешем пароля менеджера, поэтому
                    // становится недействительным сразу после смены пароля.
                    $code = $recoveryToken->create(
                        (int)$managerToRecovery->id,
                        (string)$managerToRecovery->password
                    );
                    $notify->emailPasswordRecoveryAdmin($managerToRecovery->email, $code);
                }

                // Ответ одинаков независимо от того, существует ли такой
                // менеджер: иначе форма работает как оракул для перечисления
                // администраторов сайта.
                $result->send = true;
            }
            $this->response->setContent(json_encode($result), RESPONSE_JSON);
            $this->response->sendContent();
            exit;
        }

        // Код приходит в GET по ссылке из письма и переносится через POST
        // скрытым полем, чтобы отправка формы осталась в режиме восстановления.
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
                    // Менеджер берётся из токена, а не из POST: иначе владелец
                    // любого валидного кода мог сменить пароль кому угодно.
                    $manager = $managerToRecovery;

                    // ManagersEntity::update() хеширует пароль сам.
                    $managersEntity->update((int)$manager->id, [
                        'password' => $new_password,
                        'cnt_try'  => 0,
                        'last_try' => null,
                    ]);

                    session_regenerate_id(true);
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
                    $_SESSION['admin'] = $manager->login;

                    // Старый формат хеша (APR1/MD5) заменяем на актуальный.
                    // ManagersEntity::update() хеширует пароль сам, поэтому
                    // передаём его в открытом виде.
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
