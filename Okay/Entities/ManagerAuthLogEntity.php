<?php

namespace Okay\Entities;

use Okay\Core\Entity\Entity;
use Okay\Core\Modules\Extender\ExtenderFacade;

/**
 * Журнал безпеки менеджерів (Okay\Core\Security\ManagerAuthLog).
 *
 * Фільтри оголошені явно: невідоме ім'я фільтра рушій мовчки пропускає, і
 * журнал одного менеджера показав би події всіх.
 */
class ManagerAuthLogEntity extends Entity
{
    protected static $fields = [
        'id',
        'manager_id',
        'actor_id',
        'event',
        'ip',
        'ip_bucket',
        'user_agent',
        'details',
        'created_at',
    ];

    protected static $defaultOrderFields = [
        'created_at DESC',
        'id DESC',
    ];

    protected static $table = '__manager_auth_log';
    protected static $tableAlias = 'mal';

    protected function filter__manager_id($managerId)
    {
        if ($managerId === null) {
            $this->select->where('mal.manager_id IS NULL');

            return;
        }
        $this->select->where('mal.manager_id = :manager_id');
        $this->select->bindValue('manager_id', (int)$managerId);
    }

    protected function filter__event($event)
    {
        $this->select->where('mal.event = :event');
        $this->select->bindValue('event', (string)$event);
    }

    protected function filter__ip_bucket($bucket)
    {
        $this->select->where('mal.ip_bucket = :ip_bucket');
        $this->select->bindValue('ip_bucket', (string)$bucket);
    }

    /** @param string[] $events */
    protected function filter__events($events)
    {
        $this->select->where('mal.event IN (:events)');
        $this->select->bindValue('events', array_values(array_map('strval', (array)$events)) ?: ['']);
    }

    /** @param string[] $events */
    protected function filter__not_events($events)
    {
        $this->select->where('mal.event NOT IN (:not_events)');
        $this->select->bindValue('not_events', array_values(array_map('strval', (array)$events)) ?: ['']);
    }

    /** Видаляє всі рядки менеджера — разом із ним самим. */
    public function deleteOf(int $managerId): int
    {
        $delete = $this->queryFactory->newDelete();
        $delete->from(self::getTable())
            ->where('manager_id = :manager_id')
            ->bindValue('manager_id', $managerId);

        $deleted = $this->db->query($delete) ? (int)$this->db->affectedRows() : 0;

        return ExtenderFacade::execute([static::class, __FUNCTION__], $deleted, func_get_args());
    }

    /** Не старші за $seconds (час — бази). */
    protected function filter__within($seconds)
    {
        $this->select->where('mal.created_at > NOW() - INTERVAL ' . max(1, (int)$seconds) . ' SECOND');
    }

    /** Видаляє рядки, старші за $days днів. */
    public function prune(int $days): int
    {
        $delete = $this->queryFactory->newDelete();
        $delete->from(self::getTable())
            ->where('created_at < NOW() - INTERVAL ' . max(1, $days) . ' DAY');

        $deleted = $this->db->query($delete) ? (int)$this->db->affectedRows() : 0;

        return ExtenderFacade::execute([static::class, __FUNCTION__], $deleted, func_get_args());
    }
}
