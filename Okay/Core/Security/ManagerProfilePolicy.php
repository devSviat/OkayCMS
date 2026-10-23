<?php

namespace Okay\Core\Security;

use Okay\Core\Managers;

/**
 * Хто що може з профілем і сесіями менеджера (ManagerAdmin, ManagersAdmin,
 * AllManagerSessionsAdmin).
 *
 * Свій профіль відкриває кожен менеджер: без нього не завершити свої сесії
 * й не ввімкнути 2FA. Чужі профілі, створення менеджерів і права — лише з
 * правом `managers`, сесії інших — з правом `manager_sessions`.
 *
 * Діяти над іншим менеджером (пароль, email, логін, права, сесії, видалення)
 * можна, лише коли всі його права є й у того, хто діє (covers()). Інакше
 * власник права `managers` міняв би пароль повному адміну й заходив замість
 * нього: підтвердження паролем тут не допомагає, це пароль самого нападника.
 */
class ManagerProfilePolicy
{
    public function __construct(private Managers $managers)
    {
    }

    public function canManageOthers(object $viewer): bool
    {
        return $this->managers->access('managers', $viewer);
    }

    /** Чий профіль обробляти: без права `managers` — завжди свій, хоч би що прийшло в запиті. */
    public function target(object $viewer, ?int $requestedId): ?int
    {
        if (!$this->canManageOthers($viewer)) {
            return (int)$viewer->id;
        }

        return $requestedId !== null && $requestedId > 0 ? $requestedId : null;
    }

    /** Чи пускати на сторінку: IndexAdmin::onInit знає лише id з адреси. */
    public function mayOpen(object $viewer, ?int $requestedId): bool
    {
        return $this->canManageOthers($viewer) || $requestedId === (int)$viewer->id;
    }

    /**
     * Усі права $target є й у $viewer. Права беруться вже розгорнутими
     * (Managers::setManagerPermissions): повний доступ — це повний список,
     * тож повного адміна покриває лише інший повний адмін.
     */
    public function covers(object $viewer, object $target): bool
    {
        if (!is_array($viewer->permissions ?? null) || !is_array($target->permissions ?? null)) {
            return false;
        }

        return array_diff($target->permissions, $viewer->permissions) === [];
    }

    /** Змінювати дані, пароль і видаляти: себе — так, іншого — з `managers` і лише нижчого чи рівного. */
    public function canManage(object $viewer, object $target): bool
    {
        if ((int)$target->id === (int)$viewer->id) {
            return true;
        }

        return $this->canManageOthers($viewer) && $this->covers($viewer, $target);
    }

    /** $target === null — новий менеджер. Свої права не редагує ніхто. */
    public function canEditPermissions(object $viewer, ?object $target): bool
    {
        if ($target === null) {
            return $this->canManageOthers($viewer);
        }

        return (int)$target->id !== (int)$viewer->id && $this->canManage($viewer, $target);
    }

    public function canSeeSessionsOf(object $viewer, int $targetId): bool
    {
        return $targetId === (int)$viewer->id || $this->managers->access('manager_sessions', $viewer);
    }

    public function canRevokeSessionsOf(object $viewer, object $target): bool
    {
        if ((int)$target->id === (int)$viewer->id) {
            return true;
        }

        return $this->managers->access('manager_sessions', $viewer) && $this->covers($viewer, $target);
    }
}
