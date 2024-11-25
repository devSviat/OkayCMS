<?php


namespace Okay\Modules\OkCMS\Checkbox\Controllers;



use Okay\Controllers\AbstractController;
use Okay\Modules\OkCMS\Checkbox\Entities\ReceiptsEntity;
use Okay\Modules\OkCMS\Checkbox\Entities\ShiftsEntity;
use Okay\Modules\OkCMS\Checkbox\Helpers\CheckboxMainHelper;


class CheckboxAjaxController extends AbstractController
{

    public function createShift(CheckboxMainHelper $checkboxMainHelper)
    {
        $response = $checkboxMainHelper->createShift();

        $this->response->setContent(json_encode($response), RESPONSE_JSON);
    }

    public function closeShift(CheckboxMainHelper $checkboxMainHelper)
    {
        $response = $checkboxMainHelper->closeShift();

        $this->response->setContent(json_encode($response), RESPONSE_JSON);
    }

    public function updateShift(CheckboxMainHelper $checkboxMainHelper, ShiftsEntity $shiftsEntity)
    {
        $shiftId = $this->request->post('id');
        $response = $checkboxMainHelper->checkShift($shiftId);
        $shift = $shiftsEntity->findOne(['shift_id'=>$shiftId]);
        $this->design->assign('closedShift', $shift);

        $this->design->setTemplatesDir('backend/design/html/');
        $this->design->setModuleTemplatesDir('Okay/Modules/OkCMS/Checkbox/Backend/design/html/');
        $this->design->useModuleDir();

        $response['html'] = $this->design->fetch('checkbox_shift_list.tpl');

        $this->response->setContent(json_encode($response), RESPONSE_JSON);
    }

    public function getShiftReport(CheckboxMainHelper $checkboxMainHelper)
    {
        $reportId = $this->request->post('id');
        $response = $checkboxMainHelper->getReportTxt($reportId);

        $this->response->setContent(json_encode($response), RESPONSE_JSON);
    }

    public function createReceipt(CheckboxMainHelper $checkboxMainHelper, ReceiptsEntity $receiptsEntity) {
        $orderId = $this->request->post('orderId');
        $isReturn = $this->request->post('isReturn', 'integer') ? true : false;
        $response = $checkboxMainHelper->createReceipt($orderId, $isReturn);

        if($response['id']){
            //$receipt = $receiptsEntity->findOne(['receipt_id'=>$response['id']]);
            $receipt = $receiptsEntity->findOne(['id'=>$response['id']]);
            $this->design->assign('orderReceipt', $receipt);

            $this->design->setTemplatesDir('backend/design/html/');
            $this->design->setModuleTemplatesDir('Okay/Modules/OkCMS/Checkbox/Backend/design/html/');
            $this->design->useModuleDir();

            $response['html'] = $this->design->fetch('checkbox_order_receipt.tpl');
        }

        $this->response->setContent(json_encode($response), RESPONSE_JSON);
    }

    public function getReceiptPdf(CheckboxMainHelper $checkboxMainHelper, $receiptId) {
        $response = $checkboxMainHelper->getReceiptPdf($receiptId);

        if($response['message']) {
            echo $response['message'];
        } else {
            header("Content-type: application/force-download");
            header("Content-Disposition: attachment; filename=\"$receiptId.pdf\"");
            header("Content-Length: ".filesize($response['filename']));
            readfile($response['filename']);
        }
        exit;

        //$this->response->setContent(json_encode($response), RESPONSE_JSON);
    }


}
