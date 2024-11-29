<?php

namespace Okay\Modules\Sviat\WorkingHours\Init;

use Okay\Core\Modules\EntityField;
use Okay\Core\Modules\AbstractInit;
use Okay\Modules\Sviat\WorkingHours\Entities\WorkingHoursEntity;

class Init extends AbstractInit
{
    public function install()
    {
        $this->setBackendMainController('AdminWorkingHours');
        $this->migrateEntityTable(WorkingHoursEntity::class, [
            (new EntityField('id'))->setTypeInt(11)->setAutoIncrement(),
            (new EntityField('day'))->setTypeEnum(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'], false),
            (new EntityField('closed'))->setTypeTinyInt(1),
            (new EntityField('opening_time'))->setTypeVarchar(128),
            (new EntityField('closing_time'))->setTypeVarchar(128),
        ]);
    }

    public function init()
    {
        $this->registerBackendController('AdminWorkingHours');
        $this->addBackendControllerPermission('AdminWorkingHours', 'settings');


        // $this->addFrontBlock('get_working_hours', 'working_hours.tpl');
    }
}
