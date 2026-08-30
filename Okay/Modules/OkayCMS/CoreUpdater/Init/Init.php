<?php

namespace Okay\Modules\OkayCMS\CoreUpdater\Init;

use Okay\Core\Modules\AbstractInit;
use Okay\Core\Release\CoreMigrator;
use Okay\Core\Scheduler\Schedule;
use Okay\Core\ServiceLocator;
use Okay\Modules\OkayCMS\CoreUpdater\Helpers\UpdateCheckHelper;

class Init extends AbstractInit
{
    public function install()
    {
        // Трекер самостворюваний (спец §7) - виклик тут лише пришвидшує
        // появу таблиці, ніщо від нього не залежить.
        /** @var CoreMigrator $migrator */
        $migrator = ServiceLocator::getInstance()->getService(CoreMigrator::class);
        $migrator->ensureTable();
    }

    public function init()
    {
        $this->registerSchedule(
            (new Schedule([UpdateCheckHelper::class, 'check']))
                ->name('Check for core updates')
                ->time('30 4 * * *')
                ->overlap(false)
                ->timeout(60)
        );
    }
}
