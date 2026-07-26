<?php

namespace Okay\Core\Security\Filemanager;

use Okay\Core\EntityFactory;
use Okay\Entities\ManagersEntity;

/**
 * Перевірка авторизованого менеджера для процедурних точок входу
 * файлового менеджера.
 *
 * Прапорця $_SESSION['RF']['verify'] недостатньо: він лише каже, що
 * колись відкривався діалог, і не підтверджує особу.
 */
class AccessGuard
{
    /** @var EntityFactory */
    private $entityFactory;

    public function __construct(EntityFactory $entityFactory)
    {
        $this->entityFactory = $entityFactory;
    }

    public function currentManager()
    {
        if (empty($_SESSION['admin'])) {
            return null;
        }

        /** @var ManagersEntity $managersEntity */
        $managersEntity = $this->entityFactory->get(ManagersEntity::class);
        $manager = $managersEntity->get($_SESSION['admin']);

        return empty($manager) ? null : $manager;
    }

    public function requireManager($permission = null)
    {
        $manager = $this->currentManager();

        if ($manager === null) {
            $this->deny();
        }

        if ($permission !== null && !$this->hasPermission($manager, $permission)) {
            $this->deny();
        }

        return $manager;
    }

    public function hasPermission($manager, $permission)
    {
        if (empty($manager) || empty($manager->permissions)) {
            return false;
        }

        return in_array($permission, (array)$manager->permissions, true);
    }

    private function deny()
    {
        if (!headers_sent()) {
            header('HTTP/1.1 403 Forbidden');
            header('Content-Type: text/plain; charset=utf-8');
        }

        echo 'Forbidden';
        exit;
    }
}
