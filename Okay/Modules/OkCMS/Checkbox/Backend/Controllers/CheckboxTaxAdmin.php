<?php


namespace Okay\Modules\OkCMS\Checkbox\Backend\Controllers;


use Okay\Admin\Controllers\IndexAdmin;
use Okay\Core\EntityFactory;
use Okay\Modules\OkCMS\Checkbox\Entities\TaxesEntity;

class CheckboxTaxAdmin extends IndexAdmin
{
    public function fetch(EntityFactory $entityFactory)
    {
        $taxesEntity = $entityFactory->get(TaxesEntity::class);

        $tax = new \stdClass();
        if ($this->request->method('post')) {
            $tax->id       = $this->request->post('id', 'integer');
            $tax->code     = $this->request->post('code', 'integer');
            $tax->name     = $this->request->post('name');

            if(empty($tax->code)) {
                $this->design->assign('message_error', 'empty_code');
            } elseif(empty($tax->name)) {
                $this->design->assign('message_error', 'empty_name');
            } elseif(($existTax = $taxesEntity->findOne(['code' => $tax->code])) && $existTax->id != $tax->id) {
                $this->design->assign('message_error', 'exists_code');
            } else {
                if (empty($tax->id)) {
                    $tax->id = $taxesEntity->add($tax);
                    $tax = $taxesEntity->get($tax->id);
                    $this->design->assign('message_success', 'added');
                } else {
                    $taxesEntity->update($tax->id, $tax);
                    $tax = $taxesEntity->get($tax->id);
                    $this->design->assign('message_success', 'updated');
                }
            }
        } else {
            $tax->id = $this->request->get('id', 'integer');
            $tax = $taxesEntity->findOne(['id' => $tax->id]);
        }

        $this->design->assign('tax', $tax);
        $this->response->setContent($this->design->fetch('checkbox_tax.tpl'));
    }
}