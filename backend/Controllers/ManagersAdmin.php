<?php


namespace Okay\Admin\Controllers;


use Okay\Core\Security\ManagerProfilePolicy;
use Okay\Core\Security\ManagerReauth;
use Okay\Core\Security\ManagerSessions;
use Okay\Entities\ManagersEntity;

class ManagersAdmin extends IndexAdmin
{

    public function fetch(
        ManagersEntity $managersEntity,
        ManagerSessions $managerSessions,
        ManagerProfilePolicy $policy,
        ManagerReauth $reauth
    ) {
        if ($this->request->method('post')) {
            $viewerId = (int)$this->manager->id;

            if ($this->request->post('action') === 'reauth_close') {
                $reauth->close();
                $this->postRedirectGet->redirect();
            }

            if ($this->request->post('action') === 'reauth') {
                $result = $reauth->checkPassword($viewerId, $this->request->post('reauth_password'), (string)($_SERVER['REMOTE_ADDR'] ?? ''));
                if ($this->request->post('ajax')) {
                    $this->reauthJson($result);
                }
                if ($result !== ManagerReauth::OK) {
                    $this->postRedirectGet->storeMessageError($result);
                }
                $this->postRedirectGet->redirect();
            }

            // Действия с выбранными
            $ids = $this->request->post('check');
            if (is_array($ids) && $this->request->post('action') === 'delete') {
                // Видалення — лише у вікні підтвердження, лише нижчих чи рівних
                // за правами (ManagerProfilePolicy) і не себе.
                if (!$reauth->isFresh()) {
                    $this->postRedirectGet->storeMessageError(ManagerReauth::REQUIRED);
                    $this->postRedirectGet->redirect();
                }

                $skipped = false;
                $ids = array_values(array_unique(array_map('intval', $ids)));
                $allManagers = $managersEntity->noLimit()->find() ?: [];
                foreach ($ids as $id) {
                    $target = $id > 0 ? $managersEntity->get($id) : null;
                    if (!is_object($target) || $id === $viewerId || !$policy->canManage($this->manager, $target)
                        || $policy->isLastFullAdmin($target, $allManagers, array_diff($ids, [$viewerId]))) {
                        $skipped = true;
                        continue;
                    }
                    $managersEntity->delete([$id]);
                    $managerSessions->revokeAll($id, $viewerId);
                }
                if ($skipped) {
                    $this->postRedirectGet->storeMessageError('no_access');
                }
                $this->postRedirectGet->redirect();
            }
        }

        $managers = $managersEntity->find();
        foreach ($managers as $managerAdmin) {
            $managerAdmin->can_delete = (int)$managerAdmin->id !== (int)$this->manager->id
                && $policy->canManage($this->manager, $managerAdmin);
        }
        $managersCount = $managersEntity->count();
        $this->design->assign('managers', $managers);
        $this->design->assign('managers_count', $managersCount);
        $this->design->assign('reauth_fresh', $reauth->isFresh());
        $this->design->assign('reauth_expires_at', $reauth->expiresAt());
        $this->response->setContent($this->design->fetch('managers.tpl'));
    }

    /** Відповідь на перевірку пароля з модального вікна: без перезавантаження сторінки. */
    private function reauthJson(string $result): void
    {
        $this->response->setContent(json_encode(['ok' => $result === ManagerReauth::OK, 'error' => $result === ManagerReauth::OK ? null : $result]), RESPONSE_JSON);
        $this->response->sendContent();
        exit;
    }
}
