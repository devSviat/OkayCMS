<?php

namespace Okay\Entities;

use Okay\Core\Entity\Entity;
use Okay\Core\Modules\Extender\ExtenderFacade;

/**
 * Реєстр сесій менеджерів (Okay\Core\Security\ManagerSessions).
 *
 * Фільтри, від яких залежить доступ, оголошені явно: невідоме ім'я фільтра
 * рушій мовчки пропускає, а порожній масив знімає умову, і пошук сесії
 * повернув би чужий рядок.
 */
class ManagerSessionsEntity extends Entity
{
    protected static $fields = [
        'id',
        'manager_id',
        'sid_hash',
        'created_at',
        'last_seen_at',
        'ip',
        'user_agent',
        'mfa',
        'revoked_at',
        'revoked_by',
    ];

    protected static $defaultOrderFields = [
        'last_seen_at DESC',
        'id DESC',
    ];

    protected static $table = '__manager_sessions';
    protected static $tableAlias = 'ms';

    protected function filter__sid_hash($sidHash)
    {
        $this->select->where('ms.sid_hash = :sid_hash');
        $this->select->bindValue('sid_hash', (string)$sidHash);
    }

    protected function filter__manager_id($managerId)
    {
        $this->select->where('ms.manager_id = :manager_id');
        $this->select->bindValue('manager_id', (int)$managerId);
    }

    /** Не відкликана й бачена не раніше ніж $idleSeconds тому (час — бази). */
    protected function filter__active_within($idleSeconds)
    {
        $this->select->where('ms.revoked_at IS NULL AND ms.last_seen_at > NOW() - INTERVAL ' . max(1, (int)$idleSeconds) . ' SECOND');
    }

    /**
     * Оновлює last_seen_at, якщо воно старше за $intervalSeconds: запис раз
     * на інтервал, а не на кожен ajax.
     */
    public function touch(int $id, int $intervalSeconds): void
    {
        $update = $this->queryFactory->newUpdate();
        $update->table(self::getTable())
            ->set('last_seen_at', 'NOW()')
            ->where('id = :id')
            ->where('revoked_at IS NULL')
            ->where('last_seen_at < NOW() - INTERVAL ' . max(1, $intervalSeconds) . ' SECOND')
            ->bindValue('id', $id);
        $this->db->query($update);

        ExtenderFacade::execute([static::class, __FUNCTION__], null, func_get_args());
    }

    /**
     * Відкликає сесії менеджера. $onlyIds — лише ці рядки (і лише цього
     * менеджера), $exceptSidHash — крім цієї сесії.
     *
     * @param int[]|null $onlyIds
     * @return int скільки сесій відкликано
     */
    public function revoke(int $managerId, ?array $onlyIds, ?string $exceptSidHash, ?int $byManagerId): int
    {
        if ($onlyIds !== null) {
            $onlyIds = array_values(array_filter(array_map('intval', $onlyIds)));
            if ($onlyIds === []) {
                return ExtenderFacade::execute([static::class, __FUNCTION__], 0, func_get_args());
            }
        }

        $update = $this->queryFactory->newUpdate();
        $update->table(self::getTable())
            ->set('revoked_at', 'NOW()')
            ->set('revoked_by', ':revoked_by')
            ->where('manager_id = :manager_id')
            ->where('revoked_at IS NULL')
            ->bindValue('manager_id', $managerId)
            ->bindValue('revoked_by', $byManagerId);

        if ($onlyIds !== null) {
            $update->where('id IN (:only_ids)')->bindValue('only_ids', $onlyIds);
        }
        if ($exceptSidHash !== null) {
            $update->where('sid_hash <> :except_sid_hash')->bindValue('except_sid_hash', $exceptSidHash);
        }

        $revoked = $this->db->query($update) ? (int)$this->db->affectedRows() : 0;

        return ExtenderFacade::execute([static::class, __FUNCTION__], $revoked, func_get_args());
    }

    /** Видаляє рядки, відкликані або не бачені довше за $days днів. */
    public function prune(int $days): int
    {
        $interval = 'NOW() - INTERVAL ' . max(1, $days) . ' DAY';
        $delete = $this->queryFactory->newDelete();
        $delete->from(self::getTable())
            ->where("(revoked_at IS NOT NULL AND revoked_at < {$interval}) OR last_seen_at < {$interval}");

        $deleted = $this->db->query($delete) ? (int)$this->db->affectedRows() : 0;

        return ExtenderFacade::execute([static::class, __FUNCTION__], $deleted, func_get_args());
    }
}
