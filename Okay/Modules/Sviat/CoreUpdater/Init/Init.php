<?php

namespace Okay\Modules\Sviat\CoreUpdater\Init;

use Okay\Core\Modules\AbstractInit;
use Okay\Core\Release\CoreMigrator;
use Okay\Core\Scheduler\Schedule;
use Okay\Core\ServiceLocator;
use Okay\Modules\Sviat\CoreUpdater\Helpers\UpdateCheckHelper;

class Init extends AbstractInit
{
    public function install()
    {
        // Трекер самостворюваний (спец §7) - виклик тут лише пришвидшує
        // появу таблиці, ніщо від нього не залежить.
        /** @var CoreMigrator $migrator */
        $migrator = ServiceLocator::getInstance()->getService(CoreMigrator::class);
        $migrator->ensureTable();

        $this->setBackendMainController('CoreUpdaterAdmin');
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

        $this->registerBackendController('CoreUpdaterAdmin');
        $this->addBackendControllerPermission('CoreUpdaterAdmin', 'core_updater');

        // Свій, а не свгId зі спільного svg_icon.tpl: секція шукає його там
        // лише коли для неї немає запису в additionalSectionIcons — Tabler
        // "refresh", той самий, що вже під ключем refresh_icon у ядрі.
        $this->extendBackendMenu('left_core_updater_title', [
            'left_core_updater_title' => ['CoreUpdaterAdmin'],
        ], '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4" /><path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4" /></svg>');
    }
}
