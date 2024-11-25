<?php


namespace Okay\Modules\OkCMS\Checkbox\Backend\Controllers;


use Okay\Admin\Controllers\IndexAdmin;
use Okay\Entities\OrderStatusEntity;
use Okay\Modules\OkCMS\Checkbox\Entities\ReceiptsEntity;
use Okay\Modules\OkCMS\Checkbox\Entities\ShiftsEntity;
use Okay\Modules\OkCMS\Checkbox\Helpers\CheckboxMainHelper;

class CheckboxAdmin extends IndexAdmin
{

    public function fetch(
        CheckboxMainHelper $checkboxMainHelper,
        ShiftsEntity $shiftsEntity,
        ReceiptsEntity $receiptsEntity,
        OrderStatusEntity $orderStatusEntity
    ) {
        if ($this->request->method('post')) {
            if($this->request->post('receipt_service', 'integer')) {
                $receiptValue = $this->request->post('okcms__checkbox_receipt_value', 'float');
                $checkboxMainHelper->createReceiptService($receiptValue);
            } else {
                $this->settings->set('okcms__checkbox_devMode', $this->request->post('okcms__checkbox_devMode', 'boolean'));
                $this->settings->set('okcms__checkbox_cashier_per_manager', $this->request->post('okcms__checkbox_cashier_per_manager', 'boolean'));
                $this->settings->set('okcms__checkbox_receiptText', $this->request->post('okcms__checkbox_receiptText'));
                $this->settings->set('okcms__checkbox_orderStatusId', $this->request->post('okcms__checkbox_orderStatusId', 'integer'));
                $this->settings->set('okcms__checkbox_sendMessage', $this->request->post('okcms__checkbox_sendMessage', 'integer'));
                $this->settings->set('okcms__checkbox_messageText', $this->request->post('okcms__checkbox_messageText'));

                if(!$this->settings->okcms__checkbox_cashier_per_manager) {
                    $this->settings->set('okcms__checkbox_login', $this->request->post('okcms__checkbox_login'));
                    $this->settings->set('okcms__checkbox_password', $this->request->post('okcms__checkbox_password'));
                    $this->settings->set('okcms__checkbox_licenseKey', $this->request->post('okcms__checkbox_licenseKey'));
                }
            }

            $this->postRedirectGet->storeMessageSuccess('saved');
            $this->postRedirectGet->redirect();
        }

        // Проверим все смены в статусе CLOSING и попробуем их закрыть
        foreach($shiftsEntity->find(['status'=>'CLOSING']) as $closingShift) {
            $checkboxMainHelper->checkShift($closingShift->shift_id);
        }

        // Выбираем список закрытых(и закрывающихся) смен, для просмотра
        $closedShifts = $shiftsEntity->find(['closed' => 1]);
        $this->design->assign('closedShifts', $closedShifts);

        $this->design->assign('orders_statuses', $orderStatusEntity->find());

        // Выбираем список проведенных операций внесения/вынесения средств
        $serviceReceipts = $receiptsEntity->find(['order_id' => 0, 'limit'=>10]);
        foreach($serviceReceipts as $serviceReceipt) {
            $serviceReceipt->full_response = json_decode($serviceReceipt->full_json_response, 1);
        }
        $this->design->assign('serviceReceipts', $serviceReceipts);

        $this->response->setContent($this->design->fetch('checkbox.tpl'));
    }


}
