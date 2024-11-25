<?php


namespace Okay\Modules\OkCMS\Checkbox\Backend\Controllers;

use Okay\Admin\Controllers\IndexAdmin;
use Okay\Core\EntityFactory;
use Okay\Modules\OkCMS\Checkbox\Entities\TaxesEntity;

class CheckboxTaxesAdmin extends IndexAdmin
{
    public function fetch(EntityFactory $entityFactory)
    {
        $taxesEntity = $entityFactory->get(TaxesEntity::class);

        if ($this->request->method('post')) {
            $ids = $this->request->post('check');
            if (is_array($ids)) {
                switch ($this->request->post('action')) {
                    case 'delete': {
                        $taxesEntity->delete($ids);
                        break;
                    }
                }
            }

        }

        $filter = [];
        $filter['page'] = max(1, $this->request->get('page', 'integer'));
        $filter['limit'] = 20;

        $taxes_count = $taxesEntity->count($filter);
        if($this->request->get('page') == 'all') {
            $filter['limit'] = $taxes_count;
        }

        $taxes = $taxesEntity->find($filter);
        $this->design->assign('taxes_count', $taxes_count);
        $this->design->assign('pages_count', ceil($taxes_count/$filter['limit']));
        $this->design->assign('current_page', $filter['page']);
        $this->design->assign('taxes', $taxes);
        $this->response->setContent($this->design->fetch('checkbox_taxes.tpl'));
    }
}