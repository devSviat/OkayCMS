<?php

namespace Okay\Core\Security;

use Okay\Core\Managers;

/**
 * Хто що може на сторінці профілю менеджера (ManagerAdmin).
 *
 * Свій профіль відкриває кожен менеджер: без нього не завершити свої сесії
 * й не ввімкнути 2FA. Чужі профілі, створення менеджерів і права — лише з
 * правом `managers`, як і було. Сесії інших — ще й з правом `manager_sessions`.
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

    public function canEditPermissions(object $viewer, ?int $targetId): bool
    {
        return $this->canManageOthers($viewer) && $targetId !== (int)$viewer->id;
    }

    public function canSeeSessionsOf(object $viewer, int $targetId): bool
    {
        return $targetId === (int)$viewer->id || $this->managers->access('manager_sessions', $viewer);
    }
}
