<?php

namespace Okay\Entities;

use Okay\Core\Entity\Entity;
use Okay\Core\Modules\Extender\ExtenderFacade;

/**
 * Секрет 2FA менеджера (Okay\Core\Security\ManagerTwoFactor). Фільтр за
 * менеджером оголошено явно: невідомий фільтр рушій мовчки пропускає.
 */
class ManagerTotpEntity extends Entity
{
    protected static $fields = [
        'id',
        'manager_id',
        'secret',
        'enabled_at',
        'last_step',
    ];

    protected static $defaultOrderFields = [
        'id',
    ];

    protected static $table = '__manager_totp';
    protected static $tableAlias = 'mt';

    protected function filter__manager_id($managerId)
    {
        $this->select->where('mt.manager_id = :manager_id');
        $this->select->bindValue('manager_id', (int)$managerId);
    }

    /**
     * Приймає крок, лише якщо він більший за збережений. Одним UPDATE: з двох
     * одночасних запитів з тим самим кодом проходить один.
     *
     * @return bool true — крок прийнято
     */
    public function acceptStep(int $managerId, int $step): bool
    {
        $update = $this->queryFactory->newUpdate();
        $update->table(self::getTable())
            ->set('last_step', ':step')
            ->where('manager_id = :manager_id')
            ->where('last_step < :step')
            ->bindValue('manager_id', $managerId)
            ->bindValue('step', $step);

        $accepted = $this->db->query($update) && (int)$this->db->affectedRows() === 1;

        return ExtenderFacade::execute([static::class, __FUNCTION__], $accepted, func_get_args());
    }

    public function deleteOf(int $managerId): void
    {
        $delete = $this->queryFactory->newDelete();
        $delete->from(self::getTable())
            ->where('manager_id = :manager_id')
            ->bindValue('manager_id', $managerId);
        $this->db->query($delete);

        ExtenderFacade::execute([static::class, __FUNCTION__], null, func_get_args());
    }
}
