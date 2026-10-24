<?php


namespace Okay\Admin\Controllers;


use Okay\Core\Languages;
use Okay\Core\ManagerMenu;
use Okay\Core\Security\ManagerAuthLog;
use Okay\Core\Security\ManagerProfilePolicy;
use Okay\Core\Security\ManagerReauth;
use Okay\Core\Security\ManagerSessions;
use Okay\Core\Security\ManagerTwoFactor;
use Okay\Core\Security\TotpKey;
use Okay\Core\Settings;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
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
    /** Нові коди відновлення показуються один раз — на сторінці після дії. */
    private const NEW_CODES_KEY = 'totp_new_codes';

    private ManagerTwoFactor $twoFactor;
    private ManagerAuthLog $authLog;
    private string $totpIssuer = '';

    public function fetch(
        ManagerMenu $managerMenu,
        ManagersEntity $managersEntity,
        Languages $languagesCore,
        ManagerSessions $managerSessions,
        ManagerProfilePolicy $policy,
        ManagerReauth $reauth,
        Validator $validator,
        ManagerTwoFactor $twoFactor,
        Settings $settings,
        ManagerAuthLog $authLog
    ) {
        $this->twoFactor = $twoFactor;
        $this->authLog = $authLog;
        $this->totpIssuer = trim((string)$settings->get('site_name')) ?: (string)($_SERVER['HTTP_HOST'] ?? 'OkayCMS');
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
        $this->design->assign('can_manage_target', $target === null || $policy->canManage($viewer, $target));
        $this->design->assign('can_edit_permissions', $policy->canEditPermissions($viewer, $target));
        $this->design->assign('can_see_sessions', $canSeeSessions);
        $this->design->assign('can_revoke_sessions', $target !== null && $policy->canRevokeSessionsOf($viewer, $target));
        if ($target !== null) {
            $this->assignTwoFactor($target, $isSelf);
        }
        $sessions = $canSeeSessions ? $managerSessions->activeOf((int)$target->id) : [];
        foreach ($sessions as $session) {
            $session->device = ManagerSessions::device((string)$session->user_agent);
            $session->is_mobile = ManagerSessions::isMobile((string)$session->user_agent);
        }
        $this->design->assign('sessions', $sessions);
        // Журнал видно тим самим, хто бачить сесії: це та сама картина «хто й звідки заходив».
        $this->design->assign('auth_log', $canSeeSessions ? $this->authLog->of((int)$target->id) : []);
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

        if ($action === 'reauth_close') {
            $reauth->close();
            $this->response->redirectTo($target !== null
                ? $this->profileUrl((int)$target->id, self::TAB_PROFILE)
                : $this->request->getRootUrl() . '/backend/index.php?controller=ManagerAdmin');
            return;
        }

        // Відкриває вікно підтвердження паролем того, хто діє (не цілі).
        if ($action === 'reauth') {
            $result = $reauth->checkPassword($viewerId, $this->request->post('reauth_password'), $ip);
            if ($this->request->post('ajax')) {
                $this->reauthJson($result);
            }
            if ($result !== ManagerReauth::OK) {
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

        $sessionActions = ['revoke_session', 'revoke_other_sessions'];
        $ownTwoFactorActions = ['totp_start', 'totp_confirm', 'totp_cancel', 'totp_codes'];
        if (in_array($action, $ownTwoFactorActions, true)) {
            // Підключити застосунок чи взяти нові коди можна лише собі.
            if ($isSelf) {
                $this->handleOwnTwoFactor($action, $viewer, $reauth, $managerSessions);
            }
            $this->response->redirectTo($this->profileUrl($targetId, self::TAB_SECURITY));
            return;
        }
        if (!in_array($action, array_merge(['profile', 'reset_menu', 'password', 'permissions', 'totp_disable'], $sessionActions), true)) {
            return;
        }
        $allowed = in_array($action, $sessionActions, true)
            ? $policy->canRevokeSessionsOf($viewer, $target)
            : $policy->canManage($viewer, $target);
        if (!$allowed) {
            $this->postRedirectGet->storeMessageError('no_access');
            $this->response->redirectTo($this->profileUrl($targetId, $tab));
            return;
        }
        // Над іншим менеджером — будь-яка дія лише у вікні підтвердження.
        if (!$isSelf && !$this->confirmed($reauth)) {
            $this->response->redirectTo($this->profileUrl($targetId, $tab));
            return;
        }

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
                if (!$policy->canEditPermissions($viewer, $target)) {
                    break;
                }
                if (!$this->confirmed($reauth)) {
                    break;
                }
                $newPermissions = $this->managers->determineNewPermissions(
                    $viewer,
                    $target,
                    (array)$this->request->post('permissions', null, [])
                );
                if (!$policy->hasFullAccess((object)['permissions' => array_values($newPermissions)])
                    && $policy->isLastFullAdmin($target, $managersEntity->noLimit()->find() ?: [])) {
                    $this->postRedirectGet->storeMessageError('last_full_admin');
                    break;
                }
                $managersEntity->update($targetId, ['permissions' => $newPermissions]);
                $this->postRedirectGet->storeMessageSuccess('permissions_saved');
                break;

            case 'revoke_session':
                $tab = self::TAB_SECURITY;
                // І свою теж: інакше вкрадена кука викидала б власника з інших пристроїв.
                if (!$this->confirmed($reauth)) {
                    break;
                }
                if ($managerSessions->revokeOne($targetId, (int)$this->request->post('session', 'integer'), $viewerId)) {
                    $this->postRedirectGet->storeMessageSuccess('session_revoked');
                }
                break;

            case 'revoke_other_sessions':
                $tab = self::TAB_SECURITY;
                if (!$this->confirmed($reauth)) {
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

            case 'totp_disable':
                $tab = self::TAB_SECURITY;
                if (!$this->confirmed($reauth)) {
                    break;
                }
                // Собі — ще й чинний код: вікно підтверджує пароль, а не телефон.
                // Іншому (загублений телефон) — лише вищий чи рівний, у вікні.
                if ($isSelf) {
                    $result = $this->twoFactor->verify($targetId, $this->request->post('totp_code'));
                    if ($result !== ManagerTwoFactor::OK && $result !== ManagerTwoFactor::OK_RECOVERY) {
                        $this->postRedirectGet->storeMessageError($result === ManagerTwoFactor::LIMIT ? 'totp_limit' : 'totp_wrong');
                        break;
                    }
                }
                $this->twoFactor->disable($targetId, $viewerId, 'profile');
                if ($isSelf) {
                    $managerSessions->revokeOthers($targetId, $viewerId);
                } else {
                    $managerSessions->revokeAll($targetId, $viewerId);
                }
                $this->postRedirectGet->storeMessageSuccess('totp_disabled');
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
        $this->authLog->record(ManagerAuthLog::PASSWORD_CHANGED, (int)$target->id, $isSelf ? null : $viewerId, ['via' => 'profile']);

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

    /** Відповідь на перевірку пароля з модального вікна: без перезавантаження сторінки. */
    private function reauthJson(string $result): void
    {
        $this->response->setContent(json_encode(['ok' => $result === ManagerReauth::OK, 'error' => $result === ManagerReauth::OK ? null : $result]), RESPONSE_JSON);
        $this->response->sendContent();
        exit;
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

    private function handleOwnTwoFactor(string $action, object $viewer, ManagerReauth $reauth, ManagerSessions $managerSessions): void
    {
        $viewerId = (int)$viewer->id;

        switch ($action) {
            case 'totp_start':
                if (!$this->confirmed($reauth)) {
                    return;
                }
                if ($this->twoFactor->isEnabled($viewerId)) {
                    return;
                }
                if ($this->twoFactor->startSetup($viewerId, $this->totpIssuer, (string)$viewer->login) === null) {
                    $this->postRedirectGet->storeMessageError('totp_key_missing');
                }
                return;

            case 'totp_confirm':
                $codes = $this->twoFactor->confirmSetup($viewerId, $this->stringPost('totp_code'));
                if ($codes === null) {
                    $this->postRedirectGet->storeMessageError('totp_wrong');
                    return;
                }
                $_SESSION[self::NEW_CODES_KEY] = $codes;
                // 2FA вмикають, коли пароль міг витекти: сесії, відкриті лише
                // паролем, не мають її пережити.
                $managerSessions->revokeOthers($viewerId, $viewerId);
                $this->postRedirectGet->storeMessageSuccess('totp_enabled');
                return;

            case 'totp_cancel':
                $this->twoFactor->cancelSetup();
                return;

            case 'totp_codes':
                if (!$this->confirmed($reauth) || !$this->twoFactor->isEnabled($viewerId)) {
                    return;
                }
                $_SESSION[self::NEW_CODES_KEY] = $this->twoFactor->regenerateRecoveryCodes($viewerId);
                // Старі коди могли потрапити до чужих рук — разом із ними й сесії.
                $managerSessions->revokeOthers($viewerId, $viewerId);
                $this->postRedirectGet->storeMessageSuccess('totp_codes_new');
                return;
        }
    }

    private function assignTwoFactor(object $target, bool $isSelf): void
    {
        $targetId = (int)$target->id;
        $setup = $isSelf ? $this->twoFactor->pendingSetup($targetId, $this->totpIssuer, (string)$target->login) : null;
        if ($setup !== null) {
            // Групи по 4: ключ переписують руками, суцільні 32 символи легко збити.
            $setup['secret_grouped'] = implode(' ', str_split($setup['secret'], 4));
        }
        $newCodes = $isSelf && is_array($_SESSION[self::NEW_CODES_KEY] ?? null) ? $_SESSION[self::NEW_CODES_KEY] : [];
        unset($_SESSION[self::NEW_CODES_KEY]);

        // Секрет і коди на сторінці — ні в кеш браузера, ні в проміжні кеші.
        if ($setup !== null || $newCodes !== []) {
            $this->response->addHeader('Cache-Control: no-store, private');
        }

        $this->design->assign('totp_enabled', $this->twoFactor->isEnabled($targetId));
        $this->design->assign('totp_enabled_at', $this->twoFactor->enabledAt($targetId));
        $this->design->assign('totp_codes_left', $this->twoFactor->remainingRecoveryCodes($targetId));
        $this->design->assign('totp_key_status', $this->twoFactor->keyStatus());
        $this->design->assign('totp_key_ok', $this->twoFactor->keyStatus() === TotpKey::OK);
        $this->design->assign('totp_setup', $setup);
        $this->design->assign('totp_qr', $setup !== null ? self::qrDataUri($setup['uri']) : null);
        $this->design->assign('totp_new_codes', $newCodes);
        $this->design->assign('recovery_code_used', $isSelf && !empty($_SESSION['recovery_code_used']));
        unset($_SESSION['recovery_code_used']);
    }

    /** QR малюється тут, у SVG: стороння служба QR побачила б секрет. */
    private static function qrDataUri(string $uri): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle(200, 1), new SvgImageBackEnd())))->writeString($uri);

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
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
