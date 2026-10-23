<?php

namespace Okay\Entities;

use Okay\Core\Entity\Entity;
use Okay\Core\Modules\Extender\ExtenderFacade;

/**
 * Коди відновлення 2FA: лише sha256 від коду (ManagerTwoFactor).
 */
class ManagerRecoveryCodesEntity extends Entity
{
    protected static $fields = [
        'id',
        'manager_id',
        'code_hash',
        'created_at',
        'used_at',
    ];

    protected static $defaultOrderFields = [
        'id',
    ];

    protected static $table = '__manager_recovery_codes';
    protected static $tableAlias = 'mrc';

    protected function filter__manager_id($managerId)
    {
        $this->select->where('mrc.manager_id = :manager_id');
        $this->select->bindValue('manager_id', (int)$managerId);
    }

    protected function filter__unused($unused)
    {
        $this->select->where('mrc.used_at IS NULL');
    }

    /**
     * Гасить код менеджера. Одним UPDATE: той самий код двічі не пройде навіть
     * у двох одночасних запитах.
     *
     * @return bool true — код був чинний і тепер використаний
     */
    public function consume(int $managerId, string $codeHash): bool
    {
        $update = $this->queryFactory->newUpdate();
        $update->table(self::getTable())
            ->set('used_at', 'NOW()')
            ->where('manager_id = :manager_id')
            ->where('code_hash = :code_hash')
            ->where('used_at IS NULL')
            ->bindValue('manager_id', $managerId)
            ->bindValue('code_hash', $codeHash);

        $consumed = $this->db->query($update) && (int)$this->db->affectedRows() === 1;

        return ExtenderFacade::execute([static::class, __FUNCTION__], $consumed, func_get_args());
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
