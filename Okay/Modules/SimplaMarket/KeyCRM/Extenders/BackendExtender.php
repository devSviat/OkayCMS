<?php

namespace Okay\Modules\SimplaMarket\KeyCRM\Extenders;

use Okay\Core\EntityFactory;
use Okay\Core\Modules\Extender\ExtenderFacade;
use Okay\Core\Modules\Extender\ExtensionInterface;
use Okay\Core\Request;
use Okay\Core\Design;
use Okay\Entities\OrderStatusEntity;
use Okay\Entities\OrdersEntity;
use Okay\Modules\SimplaMarket\KeyCRM\Entities\KeyCRMPaymentMethodsEntity;
use Okay\Modules\SimplaMarket\KeyCRM\Helpers\KeyCRMHelper;
use Okay\Modules\SimplaMarket\KeyCRM\Entities\KeyCRMEntity;
use Okay\Modules\SimplaMarket\KeyCRM\Entities\KeyCRMOrderEntity;

class BackendExtender implements ExtensionInterface
{
    /* Request $request */
    private $request;

    /* Design $design */
    private $design;

    /* EntityFactory $entityFactory */
    private $entityFactory;

    /* KeyCRMHelper $keyCRMHelper */
    private $keyCRMHelper;

    /* KeyCRMPaymentMethodsEntity $KeyCRMPaymentMethodsEntity */
    private $KeyCRMPaymentMethodsEntity;

    /* OrderStatusEntity $OrderStatusEntity */
    private $OrderStatusEntity;

    /* OrdersEntity $OrdersEntity */
    private $OrdersEntity;

    /* KeyCRMEntity $KeyCRMEntity */
    private $KeyCRMEntity;

    /* KeyCRMOrderEntity $KeyCRMOrderEntity */
    private $KeyCRMOrderEntity;

    public function __construct(
        Request         $request,
        Design          $design,
        EntityFactory   $entityFactory,
        KeyCRMHelper    $keyCRMHelper
    )
    {
        $this->request        = $request;
        $this->design         = $design;
        $this->entityFactory = $entityFactory;
        $this->keyCRMHelper   = $keyCRMHelper;

        $this->KeyCRMPaymentMethodsEntity = $entityFactory->get(KeyCRMPaymentMethodsEntity::class);
        $this->OrderStatusEntity          = $entityFactory->get(OrderStatusEntity::class);
        $this->OrdersEntity               = $entityFactory->get(OrdersEntity::class);
        $this->KeyCRMEntity               = $entityFactory->get(KeyCRMEntity::class);
        $this->KeyCRMOrderEntity          = $entityFactory->get(KeyCRMOrderEntity::class);
    }

    public function testKeyAPI($apiKey)
    {
        $error = "";
        if (!empty($apiKey)) {
            $error = $this->keyCRMHelper->testKeyAPIHelper($apiKey);
        }

        return ExtenderFacade::execute(__METHOD__, $error, func_get_args());
    }

    public function sourseList($apiKey)
    {
        $sourseList = "";
        if (!empty($apiKey)) {
            $sourseList = $this->keyCRMHelper->sourseListHelper($apiKey);
        }

        return ExtenderFacade::execute(__METHOD__, $sourseList, func_get_args());
    }

    public function statusList($apiKey)
    {
        $statusList = "";
        if (!empty($apiKey)) {
            $statusList = $this->keyCRMHelper->statusListHelper($apiKey);
        }

        return ExtenderFacade::execute(__METHOD__, $statusList, func_get_args());
    }

    public function paymentList($apiKey)
    {
        $paymentList = "";
        if (!empty($apiKey)) {
            $paymentList = $this->keyCRMHelper->paymentListHelper($apiKey);
        }

        return ExtenderFacade::execute(__METHOD__, $paymentList, func_get_args());
    }

    /** Получение статусов доставки и сохранение их в БД */
    public function deliveryList($apiKey)
    {
        $deliveryList = "";
        if (!empty($apiKey)) {
            $deliveryList = $this->keyCRMHelper->deliveryListHelper($apiKey);
        }

        return ExtenderFacade::execute(__METHOD__, $deliveryList, func_get_args());
    }

    public function sendOrder($order)
    {
        $sendOrder = "";
        if (!empty($order)) {   //  посылаем заказ в CRM с проверкой на его наличие в CRM
            $sendOrder = $this->keyCRMHelper->OrderRequest($order, 1);
        }

        return ExtenderFacade::execute(__METHOD__, $order, func_get_args());
    }

    public function sendPayedOrder($order)
    {
        if (!empty($order)
            && $this->request->method('post')
            && $checkboxInOrder = $this->request->post('send_to_CRM')   //  нажата ли галочка в заказе на отправку
        ) {
            $orderMain = $order;
            $statusOrderAccess  = $this->OrderStatusEntity->findOne(['id' => $orderMain->status_id]);
            $orderKeyCRMO       = $this->KeyCRMOrderEntity->findOne(['source_uuid' => $orderMain->id]);      //  source_uuid - id заказа в OkayCMS
            $RemoteKeyCRMROrder = $this->keyCRMHelper->getCRMOrder($orderMain->id); //  узнаем о заказе в KeyCRM

            if (empty($orderKeyCRMO)
                && !empty($RemoteKeyCRMROrder)
            ) {
                $this->keyCRMHelper->addRecordToKeyCRMOrder($RemoteKeyCRMROrder);
                $orderKeyCRMO = $this->KeyCRMOrderEntity->findOne(['source_uuid' => $orderMain->id]);      //  source_uuid - id заказа в OkayCMS
            }

            if (!empty($statusOrderAccess) && !empty($statusOrderAccess->sendStatusCRM) && ($statusOrderAccess->sendStatusCRM == 1)   //  если данный статус заказа установлен к отправке
            ) {
                if (empty($RemoteKeyCRMROrder) //  и заказа нет в CRM
                ) { //  если нет данных о заказе из таблицы отправленных в CRM
                    //   то выполняем отправку нового заказа в CRM
                    $sendOrder = $this->keyCRMHelper->OrderRequest($orderMain);

                    //  актуализируем из CRM заказ после сохранения
                    $RemoteKeyCRMROrder = $this->keyCRMHelper->getCRMOrder($orderMain->id); //  узнаем о заказе в KeyCRM

                    //  работаем с ошибками
                    if (!empty($orderMain->id)) {
                        if (!empty($sendOrder) && !empty($sendOrder->errors)) {
                            $this->OrdersEntity->update($orderMain->id, ['error_crm' => '['. date('d-m-Y H:i:s') .'] '.print_r($sendOrder->errors,1)]); //  очищаем ошибку
                        } else {
                            $this->OrdersEntity->update($orderMain->id, ['error_crm' => '']); //  очищаем ошибку
                        }
                    }

                } else if (!empty($orderKeyCRMO)   //  если заказ уже отправлялся в CRM то обновляем заказ
                ) {
                    //  обновляем заказ
                    $sendOrder = $this->keyCRMHelper->OrderRequestPUT($orderMain, 0, $RemoteKeyCRMROrder);

                    //  работаем с ошибками
                    if (!empty($orderMain->id)) {
                        if (!empty($sendOrder) && !empty($sendOrder->errors)) {
                            $this->OrdersEntity->update($orderMain->id, ['error_crm' => '['. date('d-m-Y H:i:s') .'] '.print_r($sendOrder->errors,1)]); //  очищаем ошибку
                        } else {
                            $this->OrdersEntity->update($orderMain->id, ['error_crm' => '']); //  очищаем ошибку
                        }
                    }
                }
            }

            if (empty($RemoteKeyCRMROrder)) {   //  если нет в CRM
                $this->design->assign("crm_info", null);
            } else {
                $this->design->assign("crm_info", $orderKeyCRMO);
            }
        }

        return ExtenderFacade::execute(__METHOD__, $order, func_get_args());
    }

    public function tplOrderAddInfo($order)
    {
        $orderKeyCRMO = '';
        if (!empty($order) && !empty($order->id)) {
            $RemoteKeyCRMROrder = $this->keyCRMHelper->getCRMOrder($order->id); //  узнаем о заказе в KeyCRM
            $orderKeyCRMO = $this->KeyCRMOrderEntity->findOne(['source_uuid' => $order->id]);     //  source_uuid - id заказа в OkayCMS
        }

        if (empty($RemoteKeyCRMROrder)) {   //  если нет в CRM
            $this->design->assign("crm_info", null);
        } else {
            $this->design->assign("crm_info", $orderKeyCRMO);
        }

        return ExtenderFacade::execute(__METHOD__, $order, func_get_args());
    }

}