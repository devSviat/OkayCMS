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
        'failed_count',
        'failed_since',
        'lockouts',
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

    /**
     * Записує новий стан лічильника хибних кодів, лише якщо в базі досі той,
     * з якого його рахували: з двох одночасних спроб останнє місце займає одна.
     *
     * @param array{0: int, 1: int, 2: int} $expected [failed_count, failed_since, lockouts]
     * @param array{0: int, 1: int, 2: int} $next
     * @return bool true — записано
     */
    public function swapAttempts(int $managerId, array $expected, array $next): bool
    {
        $update = $this->queryFactory->newUpdate();
        $update->table(self::getTable())
            ->set('failed_count', ':next_count')
            ->set('failed_since', ':next_since')
            ->set('lockouts', ':next_lockouts')
            ->where('manager_id = :manager_id')
            ->where('failed_count = :count')
            ->where('failed_since = :since')
            ->where('lockouts = :lockouts')
            ->bindValues([
                'manager_id'    => $managerId,
                'count'         => $expected[0],
                'since'         => $expected[1],
                'lockouts'      => $expected[2],
                'next_count'    => $next[0],
                'next_since'    => $next[1],
                'next_lockouts' => $next[2],
            ]);

        $swapped = $this->db->query($update) && (int)$this->db->affectedRows() === 1;

        return ExtenderFacade::execute([static::class, __FUNCTION__], $swapped, func_get_args());
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
