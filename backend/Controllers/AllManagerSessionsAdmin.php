<?php

namespace Okay\Admin\Controllers;

use Okay\Core\Security\ManagerSessions;
use Okay\Entities\ManagersEntity;

/**
 * Огляд: хто зараз увійшов. Доступ — право manager_sessions (Managers).
 * Завершують сесії в профілі менеджера (ManagerAdmin, вкладка «Безпека»):
 * одна точка для дій і для підтвердження паролем.
 */
class AllManagerSessionsAdmin extends IndexAdmin
{
    public function fetch(ManagerSessions $managerSessions, ManagersEntity $managersEntity)
    {
        $sessions = $managerSessions->activeOfAll();
        $managerIds = array_values(array_unique(array_map(static fn($s) => (int)$s->manager_id, $sessions)));
        $logins = [];
        if ($managerIds !== []) {
            foreach ($managersEntity->noLimit()->find(['id' => $managerIds]) ?: [] as $m) {
                $logins[(int)$m->id] = (string)$m->login;
            }
        }

        $current = $managerSessions->currentRow();

        $this->design->assign('sessions', $sessions);
        $this->design->assign('session_logins', $logins);
        $this->design->assign('current_session_id', $current !== null ? (int)$current->id : 0);
        $this->design->assign('can_open_profiles', $this->managers->access('managers', $this->manager));

        $this->response->setContent($this->design->fetch('manager_sessions.tpl'));
    }
}
