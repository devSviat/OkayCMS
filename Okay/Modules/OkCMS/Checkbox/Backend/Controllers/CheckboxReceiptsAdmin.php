<?php


namespace Okay\Modules\OkCMS\Checkbox\Backend\Controllers;


use Okay\Admin\Controllers\IndexAdmin;
use Okay\Modules\OkCMS\Checkbox\Entities\ReceiptsEntity;

class CheckboxReceiptsAdmin extends IndexAdmin
{

    public function fetch(
        ReceiptsEntity $receiptsEntity
    ) {

        $filter = [];
        $filter['order_id'] = 0;
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

        $shiftsCount = $receiptsEntity->count($filter);
        if ($this->request->get('page') == 'all') {
            $filter['limit'] = $shiftsCount;
        }
        if ($filter['limit']>0) {
            $pagesCount = ceil($shiftsCount/$filter['limit']);
        } else {
            $pagesCount = 0;
        }
        $filter['page'] = min($filter['page'], $pagesCount);


        $serviceReceipts = $receiptsEntity->find($filter);
        foreach($serviceReceipts as $serviceReceipt) {
            $serviceReceipt->full_response = json_decode($serviceReceipt->full_json_response, 1);
        }

        $this->design->assign('serviceReceipts', $serviceReceipts);
        $this->design->assign('pages_count',  $pagesCount);
        $this->design->assign('current_page', $filter['page']);

        if (isset($filter['from_date'])) {
            $this->design->assign('from_date', $filter['from_date']);
        }

        if (isset($filter['to_date'])) {
            $this->design->assign('to_date', $filter['to_date']);
        }

        $this->response->setContent($this->design->fetch('checkbox_receipts.tpl'));
    }


}
