<?php


namespace Okay\Modules\OkCMS\Checkbox\Extenders;


use Okay\Core\Design;
use Okay\Core\EntityFactory;
use Okay\Core\Modules\Extender\ExtensionInterface;
use Okay\Core\Request;
use Okay\Core\ServiceLocator;
use Okay\Core\Settings;
use Okay\Modules\OkCMS\Checkbox\Entities\ReceiptsEntity;
use Okay\Modules\OkCMS\Checkbox\Entities\ShiftsEntity;
use Okay\Modules\OkCMS\Checkbox\Entities\TaxesEntity;
use Okay\Modules\OkCMS\Checkbox\Helpers\CheckboxMainHelper;
use Okay\Modules\OkCMS\Checkbox\Init\Init;

class BackendExtender implements ExtensionInterface
{

    private $design;
    private $request;
    private $settings;
    private $entityFactory;
    private $checkboxMainHelper;

    public function __construct() {
        $SL = ServiceLocator::getInstance();
        $this->design = $SL->getService(Design::class);
        $this->request = $SL->getService(Request::class);
        $this->settings = $SL->getService(Settings::class);
        $this->entityFactory = $SL->getService(EntityFactory::class);
        $this->checkboxMainHelper = $SL->getService(CheckboxMainHelper::class);
    }

    public function initCheckbox() {

        $this->checkboxMainHelper->getToken();

        $shiftsEntity = $this->entityFactory->get(ShiftsEntity::class);
        $activeShift = $shiftsEntity->getActiveShift();
        if($activeShift) {
            if ($activeShift->status == 'CREATED') {
                $this->checkboxMainHelper->checkShift($activeShift->shift_id);
                $activeShift = $shiftsEntity->getActiveShift();
            }
            $this->design->assign('checkboxActiveShift', $activeShift);
        }
        $receiptsEntity = $this->entityFactory->get(ReceiptsEntity::class);
        $this->design->assign('emptyReceiptsCount', $receiptsEntity->count(['receipt_id'=>'']));
    }

    public function initCheckboxForOrder($order, $orderId) {
        $receiptsEntity = $this->entityFactory->get(ReceiptsEntity::class);
        $orderReceipts = $receiptsEntity->find(['order_id' => $order->id]);
        $this->design->assign('orderReceipts', $orderReceipts);
        $this->design->assign('emptyOrderReceiptsCount', $receiptsEntity->count(['receipt_id'=>'','order_id' => $order->id]));
    }

    public function getProduct($product) {
        $taxesEntity = $this->entityFactory->get(TaxesEntity::class);
        $checkboxTaxes = $taxesEntity->find();
        $this->design->assign('checkboxTaxes', $checkboxTaxes);

        $this->design->assign('checkboxProductTaxes', $taxesEntity->getProductTaxes($product->id));
    }

    public function postProduct($product) {
        $taxesEntity = $this->entityFactory->get(TaxesEntity::class);
        $productTaxes = $this->request->post('checkboxTaxes');

        $taxesEntity->deleteProductTaxes($product->id);
        foreach ($productTaxes as $productTax) {
            $taxesEntity->addProductTax($product->id, $productTax);
        }
    }

    public function findOrders($orders) {
        $receiptsEntity = $this->entityFactory->get(ReceiptsEntity::class);

        foreach($orders as $order) {
            $order->receipt = $receiptsEntity->findOne(['order_id'=>$order->id]);
        }

        //return $orders;
    }

    public function postPayment($paymentMethod) {
        $paymentMethod->{Init::CHECKBOX_PAYMENT_TYPE_FIELD} = $this->request->post(Init::CHECKBOX_PAYMENT_TYPE_FIELD);
        //return $paymentMethod;
    }

    public function orderMarkedPaid($output, $ids, $state) {
        //$this->checkboxMainHelper->createReceiptsByOrderPaid($ids, $state);
    }

    public function updateOrderStatus($ressultUpdate, $order, $orderStatusId) {
        $checkboxStatusId = (int)$this->settings->get('okcms__checkbox_orderStatusId');
        $shiftsEntity = $this->entityFactory->get(ShiftsEntity::class);
        $activeShift = $shiftsEntity->getActiveShift();
        if($checkboxStatusId && $checkboxStatusId == $orderStatusId && $activeShift) {
            /* @var ReceiptsEntity $receiptsEntity */
            $receiptsEntity = $this->entityFactory->get(ReceiptsEntity::class);
            $orderReceipt = $receiptsEntity->order('id_desc')->findOne(['order_id' => $order->id]);
            if(!$orderReceipt || $orderReceipt->is_return) {
                $this->checkboxMainHelper->createReceipt($order->id, false, null, true);
            }
        }
    }

}