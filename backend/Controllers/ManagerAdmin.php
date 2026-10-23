<?php


namespace Okay\Admin\Controllers;


use Okay\Core\Languages;
use Okay\Core\ManagerMenu;
use Okay\Core\Security\ManagerProfilePolicy;
use Okay\Core\Security\ManagerReauth;
use Okay\Core\Security\ManagerSessions;
use Okay\Core\Validator;
use Okay\Entities\ManagersEntity;

/**
 * Профіль менеджера: вкладки «Профіль», «Безпека» (пароль, сесії) і «Права
 * доступу». Кожна дія — окрема форма з полем action, тож завершення сесії не
 * відправляє заодно профіль і права.
 *
 * Хто що може — ManagerProfilePolicy. Зміна пароля, email, логіна, прав і
 * чужих сесій питає пароль того, хто діє (ManagerReauth).
 */
class ManagerAdmin extends IndexAdmin
{
    public const PASSWORD_MIN_LENGTH = 8;

    private const TAB_PROFILE = 'profile';
    private const TAB_SECURITY = 'security';
    private const TAB_PERMISSIONS = 'permissions';

    public function fetch(
        ManagerMenu $managerMenu,
        ManagersEntity $managersEntity,
        Languages $languagesCore,
        ManagerSessions $managerSessions,
        ManagerProfilePolicy $policy,
        ManagerReauth $reauth,
        Validator $validator
    ) {
        $viewer = $this->manager;
        $viewerId = (int)$viewer->id;
        $btrLanguages = $this->btrLanguages($languagesCore);

        $requestedId = $this->request->method('post')
            ? ($this->request->post('id', 'integer') ?: null)
            : ($this->request->get('id', 'integer') ?: null);
        $targetId = $policy->target($viewer, $requestedId);
        $target = $targetId !== null ? $managersEntity->get($targetId) : null;

        if ($targetId !== null && !is_object($target)) {
            $this->design->assign('not_found', true);
            $this->response->setContent($this->design->fetch('manager.tpl'));
            return;
        }

        if ($this->request->method('post')) {
            $this->handlePost(
                (string)$this->request->post('action'),
                $viewer,
                $target,
                $managersEntity,
                $managerSessions,
                $policy,
                $reauth,
                $validator,
                $btrLanguages
            );
        }

        $isSelf = $target !== null && (int)$target->id === $viewerId;
        $canSeeSessions = $target !== null && $policy->canSeeSessionsOf($viewer, (int)$target->id);
        $current = $managerSessions->currentRow();

        $this->design->assign('m', $target ?? new \stdClass());
        $this->design->assign('is_new', $target === null);
        $this->design->assign('is_self', $isSelf);
        $this->design->assign('can_edit_permissions', $policy->canEditPermissions($viewer, $target !== null ? (int)$target->id : null));
        $this->design->assign('can_see_sessions', $canSeeSessions);
        $sessions = $canSeeSessions ? $managerSessions->activeOf((int)$target->id) : [];
        foreach ($sessions as $session) {
            $session->device = ManagerSessions::device((string)$session->user_agent);
        }
        $this->design->assign('sessions', $sessions);
        $this->design->assign('current_session_id', $current !== null ? (int)$current->id : 0);
        $this->design->assign('reauth_fresh', $reauth->isFresh());
        $this->design->assign('reauth_expires_at', $reauth->expiresAt());
        $this->design->assign('password_min_length', self::PASSWORD_MIN_LENGTH);
        $this->design->assign('btr_languages', $btrLanguages);
        $this->design->assign('permission', $managerMenu->getPermissionMenu($viewer, $this->design->getVar('btr')));

        $this->response->setContent($this->design->fetch('manager.tpl'));
    }

    /**
     * Виконує одну дію й завершується редіректом (PRG). Повертається лише
     * тоді, коли дії не було.
     */
    private function handlePost(
        string $action,
        object $viewer,
        ?object $target,
        ManagersEntity $managersEntity,
        ManagerSessions $managerSessions,
        ManagerProfilePolicy $policy,
        ManagerReauth $reauth,
        Validator $validator,
        array $btrLanguages
    ): void {
        $viewerId = (int)$viewer->id;
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');

        // Відкриває вікно підтвердження паролем того, хто діє (не цілі).
        if ($action === 'reauth') {
            if (($result = $reauth->checkPassword($viewerId, $this->request->post('reauth_password'), $ip)) !== ManagerReauth::OK) {
                $this->postRedirectGet->storeMessageError($result);
            }
            $tab = $this->request->post('tab');
            $tab = in_array($tab, [self::TAB_PROFILE, self::TAB_SECURITY, self::TAB_PERMISSIONS], true) ? $tab : self::TAB_PROFILE;
            $this->response->redirectTo($target !== null
                ? $this->profileUrl((int)$target->id, $tab)
                : $this->request->getRootUrl() . '/backend/index.php?controller=ManagerAdmin');
            return;
        }

        if ($target === null) {
            if ($action === 'create' && $policy->canManageOthers($viewer)) {
                $this->createManager($viewer, $managersEntity, $reauth, $validator, $btrLanguages, $ip);
            }
            return;
        }

        $targetId = (int)$target->id;
        $isSelf = $targetId === $viewerId;
        $tab = self::TAB_PROFILE;

        switch ($action) {
            case 'profile':
                $this->saveProfile($target, $isSelf, $managersEntity, $managerSessions, $reauth, $validator, $btrLanguages, $ip, $viewerId);
                break;

            case 'reset_menu':
                $managersEntity->update($targetId, ['menu' => '']);
                $this->postRedirectGet->storeMessageSuccess('updated');
                break;

            case 'password':
                $tab = self::TAB_SECURITY;
                $this->changePassword($target, $isSelf, $managersEntity, $managerSessions, $reauth, $ip, $viewerId);
                break;

            case 'permissions':
                $tab = self::TAB_PERMISSIONS;
                if (!$policy->canEditPermissions($viewer, $targetId)) {
                    break;
                }
                if (!$this->confirmed($reauth)) {
                    break;
                }
                $managersEntity->update($targetId, [
                    'permissions' => $this->managers->determineNewPermissions(
                        $viewer,
                        $target,
                        (array)$this->request->post('permissions', null, [])
                    ),
                ]);
                $this->postRedirectGet->storeMessageSuccess('permissions_saved');
                break;

            case 'revoke_session':
                $tab = self::TAB_SECURITY;
                if (!$policy->canSeeSessionsOf($viewer, $targetId)) {
                    break;
                }
                // Свою сесію завершують без пароля, чужу — лише з підтвердженням.
                if (!$isSelf && !$this->confirmed($reauth)) {
                    break;
                }
                if ($managerSessions->revokeOne($targetId, (int)$this->request->post('session', 'integer'), $viewerId)) {
                    $this->postRedirectGet->storeMessageSuccess('session_revoked');
                }
                break;

            case 'revoke_other_sessions':
                $tab = self::TAB_SECURITY;
                if (!$policy->canSeeSessionsOf($viewer, $targetId)) {
                    break;
                }
                if (!$isSelf && !$this->confirmed($reauth)) {
                    break;
                }
                // Для себе — усі, крім цієї; для іншого менеджера — усі.
                if ($isSelf) {
                    $managerSessions->revokeOthers($targetId, $viewerId);
                } else {
                    $managerSessions->revokeAll($targetId, $viewerId);
                }
                $this->postRedirectGet->storeMessageSuccess('sessions_revoked');
                break;

            default:
                return;
        }

        $this->response->redirectTo($this->profileUrl($targetId, $tab));
    }

    private function saveProfile(
        object $target,
        bool $isSelf,
        ManagersEntity $managersEntity,
        ManagerSessions $managerSessions,
        ManagerReauth $reauth,
        Validator $validator,
        array $btrLanguages,
        string $ip,
        int $viewerId
    ): void {
        $targetId = (int)$target->id;
        $login = $this->stringPost('login');
        $email = trim($this->stringPost('email'));
        $lang = $this->stringPost('manager_lang');

        if ($login === '' || trim($login) !== $login) {
            $this->postRedirectGet->storeMessageError('empty_login');
            return;
        }
        if (($existing = $managersEntity->get($login)) && (int)$existing->id !== $targetId) {
            $this->postRedirectGet->storeMessageError('login_exists');
            return;
        }
        if (!$validator->isEmail($email)) {
            $this->postRedirectGet->storeMessageError('email_invalid');
            return;
        }

        // Логін і email — це вхід і відновлення пароля: змінити їх самою
        // кукою значило б забрати обліковий запис.
        $sensitive = $login !== (string)$target->login || $email !== (string)$target->email;
        if ($sensitive && !$this->confirmed($reauth)) {
            return;
        }

        $managersEntity->update($targetId, [
            'login'       => $login,
            'email'       => $email,
            'lang'        => in_array($lang, $btrLanguages, true) ? $lang : (string)$target->lang,
            'comment'     => mb_substr($this->stringPost('comment'), 0, 512),
            'menu_status' => $this->request->post('menu_status', 'integer') ? 1 : 0,
        ]);

        if ($isSelf && $login !== (string)$target->login) {
            $managerSessions->loginChanged($targetId, $login);
        }

        $this->postRedirectGet->storeMessageSuccess('updated');
    }

    private function changePassword(
        object $target,
        bool $isSelf,
        ManagersEntity $managersEntity,
        ManagerSessions $managerSessions,
        ManagerReauth $reauth,
        string $ip,
        int $viewerId
    ): void {
        $password = $this->stringPost('new_password');

        if (trim($password) === '') {
            $this->postRedirectGet->storeMessageError('password_empty');
            return;
        }
        if (mb_strlen($password) < self::PASSWORD_MIN_LENGTH) {
            $this->postRedirectGet->storeMessageError('password_short');
            return;
        }
        if (!hash_equals($password, $this->stringPost('new_password_check'))) {
            $this->postRedirectGet->storeMessageError('password_mismatch');
            return;
        }

        // Свій пароль — завжди з поточним, навіть у вікні підтвердження; чужий — у вікні.
        if ($isSelf) {
            if (($result = $reauth->checkPassword($viewerId, $this->request->post('current_password'), $ip)) !== ManagerReauth::OK) {
                $this->postRedirectGet->storeMessageError($result);
                return;
            }
        } elseif (!$this->confirmed($reauth)) {
            return;
        }

        // ManagersEntity::update() хешує пароль сам.
        $managersEntity->update((int)$target->id, ['password' => $password]);

        if ($isSelf) {
            $managerSessions->revokeOthers((int)$target->id, $viewerId);
        } else {
            $managerSessions->revokeAll((int)$target->id, $viewerId);
        }

        $this->postRedirectGet->storeMessageSuccess('password_changed');
    }

    private function createManager(
        object $viewer,
        ManagersEntity $managersEntity,
        ManagerReauth $reauth,
        Validator $validator,
        array $btrLanguages,
        string $ip
    ): void {
        $login = $this->stringPost('login');
        $email = trim($this->stringPost('email'));
        $password = $this->stringPost('new_password');
        $lang = $this->stringPost('manager_lang');
        $error = null;

        if ($login === '' || trim($login) !== $login) {
            $error = 'empty_login';
        } elseif ($managersEntity->get($login)) {
            $error = 'login_exists';
        } elseif (!$validator->isEmail($email)) {
            $error = 'email_invalid';
        } elseif (trim($password) === '' || mb_strlen($password) < self::PASSWORD_MIN_LENGTH) {
            $error = 'password_short';
        } elseif (!hash_equals($password, $this->stringPost('new_password_check'))) {
            $error = 'password_mismatch';
        } elseif (!$reauth->isFresh()) {
            $error = ManagerReauth::REQUIRED;
        }

        if ($error !== null) {
            $this->postRedirectGet->storeMessageError($error);
            $this->response->redirectTo($this->request->getRootUrl() . '/backend/index.php?controller=ManagerAdmin');
            return;
        }

        $id = $managersEntity->add([
            'login'       => $login,
            'email'       => $email,
            'password'    => $password,
            'lang'        => in_array($lang, $btrLanguages, true) ? $lang : 'en',
            'comment'     => mb_substr($this->stringPost('comment'), 0, 512),
            'menu_status' => 1,
            // Видати можна лише ті права, що є в того, хто створює.
            'permissions' => $this->managers->determineNewPermissions(
                $viewer,
                null,
                (array)$this->request->post('permissions', null, [])
            ),
        ]);

        if (!$id) {
            $this->postRedirectGet->storeMessageError('save_failed');
            $this->response->redirectTo($this->request->getRootUrl() . '/backend/index.php?controller=ManagerAdmin');
            return;
        }

        $this->postRedirectGet->storeMessageSuccess('added');
        $this->response->redirectTo($this->profileUrl((int)$id, self::TAB_PROFILE));
    }

    /** Чутлива дія лише у вікні підтвердження; інакше — повідомлення, а пароль тут не питається. */
    private function confirmed(ManagerReauth $reauth): bool
    {
        if ($reauth->isFresh()) {
            return true;
        }
        $this->postRedirectGet->storeMessageError(ManagerReauth::REQUIRED);

        return false;
    }

    private function stringPost(string $name): string
    {
        $value = $this->request->post($name);

        return is_string($value) ? $value : '';
    }

    private function profileUrl(int $id, string $tab): string
    {
        return $this->request->getRootUrl() . '/backend/index.php?controller=ManagerAdmin&id=' . $id . '&active_tab=' . $tab;
    }

    /** @return array<string, string> назва => мітка, лише мови з файлом перекладу */
    private function btrLanguages(Languages $languagesCore): array
    {
        $btrLanguages = [];
        foreach ($languagesCore->getLangList() as $label => $l) {
            if (file_exists('backend/lang/' . $label . '.php')) {
                $btrLanguages[$l->name] = $l->label;
            }
        }

        return $btrLanguages;
    }
}
