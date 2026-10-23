<?php

namespace Okay\Admin\Controllers;

use Okay\Core\Security\ManagerProfilePolicy;
use Okay\Core\Security\ManagerReauth;
use Okay\Core\Security\ManagerSessions;
use Okay\Entities\ManagerSessionsEntity;
use Okay\Entities\ManagersEntity;

/**
 * Хто зараз увійшов. Доступ — право manager_sessions (Managers).
 *
 * Завершити сесію можна й тут, і в профілі менеджера: власник
 * manager_sessions без права managers чужих профілів не відкриває. Правила
 * ті самі — вікно підтвердження паролем і лише менеджера, чиї права є й у
 * того, хто діє (ManagerProfilePolicy::canRevokeSessionsOf).
 */
class AllManagerSessionsAdmin extends IndexAdmin
{
    public function fetch(
        ManagerSessions $managerSessions,
        ManagersEntity $managersEntity,
        ManagerSessionsEntity $sessionsEntity,
        ManagerProfilePolicy $policy,
        ManagerReauth $reauth
    ) {
        $viewer = $this->manager;
        $viewerId = (int)$viewer->id;

        if ($this->request->method('post')) {
            if ($this->request->post('action') === 'reauth') {
                $result = $reauth->checkPassword($viewerId, $this->request->post('reauth_password'), (string)($_SERVER['REMOTE_ADDR'] ?? ''));
                if ($result !== ManagerReauth::OK) {
                    $this->postRedirectGet->storeMessageError($result);
                }
            } elseif ($this->request->post('action') === 'revoke_session') {
                $row = $sessionsEntity->get((int)$this->request->post('session', 'integer'));
                $owner = is_object($row) ? $managersEntity->get((int)$row->manager_id) : null;

                if (!$reauth->isFresh()) {
                    $this->postRedirectGet->storeMessageError(ManagerReauth::REQUIRED);
                } elseif (!is_object($owner) || !$policy->canRevokeSessionsOf($viewer, $owner)) {
                    $this->postRedirectGet->storeMessageError('no_access');
                } elseif ($managerSessions->revokeOne((int)$owner->id, (int)$row->id, $viewerId)) {
                    $this->postRedirectGet->storeMessageSuccess('session_revoked');
                }
            }
            $this->postRedirectGet->redirect();
        }

        $sessions = $managerSessions->activeOfAll();
        $managerIds = array_values(array_unique(array_map(static fn($s) => (int)$s->manager_id, $sessions)));
        $owners = [];
        if ($managerIds !== []) {
            foreach ($managersEntity->noLimit()->find(['id' => $managerIds]) ?: [] as $m) {
                $owners[(int)$m->id] = $m;
            }
        }

        foreach ($sessions as $session) {
            $owner = $owners[(int)$session->manager_id] ?? null;
            $session->login = $owner !== null ? (string)$owner->login : '#' . (int)$session->manager_id;
            $session->can_revoke = $owner !== null && $policy->canRevokeSessionsOf($viewer, $owner);
            $session->device = ManagerSessions::device((string)$session->user_agent);
            $session->is_mobile = ManagerSessions::isMobile((string)$session->user_agent);
        }

        $current = $managerSessions->currentRow();

        $this->design->assign('sessions', $sessions);
        $this->design->assign('current_session_id', $current !== null ? (int)$current->id : 0);
        $this->design->assign('can_open_profiles', $policy->canManageOthers($viewer));
        $this->design->assign('reauth_fresh', $reauth->isFresh());
        $this->design->assign('reauth_expires_at', $reauth->expiresAt());

        $this->response->setContent($this->design->fetch('manager_sessions.tpl'));
    }
}
