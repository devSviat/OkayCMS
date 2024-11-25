<?php


namespace Okay\Modules\OkCMS\Checkbox\Backend\Controllers;


use Okay\Admin\Controllers\IndexAdmin;
use Okay\Modules\OkCMS\Checkbox\Entities\ShiftsEntity;
use Okay\Modules\OkCMS\Checkbox\Helpers\CheckboxMainHelper;

class CheckboxShiftsAdmin extends IndexAdmin
{

    public function fetch(
        CheckboxMainHelper $checkboxMainHelper,
        ShiftsEntity $shiftsEntity
    ) {

        // Проверим все смены в статусе CLOSING и попробуем их закрыть
        foreach($shiftsEntity->find(['status'=>'CLOSING']) as $closingShift) {
            $checkboxMainHelper->checkShift($closingShift->shift_id);
        }

        $filter = [];
        $filter['page'] = max(1, $this->request->get('page', 'integer'));
        $filter['limit'] = 20;

        $fromDate = $this->request->get('from_date');
        $toDate = $this->request->get('to_date');
        if (!empty($fromDate)){
            $filter['from_date'] = $fromDate;
        }
        if (!empty($toDate)){
            $filter['to_date'] = $toDate;
        }
        $filter['closed'] = 1;

        $shiftsCount = $shiftsEntity->count($filter);
        if ($this->request->get('page') == 'all') {
            $filter['limit'] = $shiftsCount;
        }
        if ($filter['limit']>0) {
            $pagesCount = ceil($shiftsCount/$filter['limit']);
        } else {
            $pagesCount = 0;
        }
        $filter['page'] = min($filter['page'], $pagesCount);


        $closedShifts = $shiftsEntity->find($filter);

        $this->design->assign('closedShifts', $closedShifts);
        $this->design->assign('pages_count',  $pagesCount);
        $this->design->assign('current_page', $filter['page']);

        if (isset($filter['from_date'])) {
            $this->design->assign('from_date', $filter['from_date']);
        }

        if (isset($filter['to_date'])) {
            $this->design->assign('to_date', $filter['to_date']);
        }

        $this->response->setContent($this->design->fetch('checkbox_shifts.tpl'));
    }


}
