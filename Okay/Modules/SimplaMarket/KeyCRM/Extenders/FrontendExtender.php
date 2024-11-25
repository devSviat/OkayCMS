<?php

namespace Okay\Modules\SimplaMarket\KeyCRM\Extenders;

use Okay\Core\EntityFactory;
use Okay\Core\Modules\Extender\ExtenderFacade;
use Okay\Core\Modules\Extender\ExtensionInterface;
use Okay\Core\Request;
use Okay\Core\Design;
use Okay\Core\Settings;
use Okay\Core\UserReferer\UserReferer;
use Okay\Entities\OrderStatusEntity;
use Okay\Entities\OrdersEntity;
use Okay\Modules\SimplaMarket\KeyCRM\Entities\KeyCRMPaymentMethodsEntity;
use Okay\Modules\SimplaMarket\KeyCRM\Helpers\KeyCRMHelper;
use Okay\Modules\SimplaMarket\KeyCRM\Entities\KeyCRMEntity;
use Okay\Modules\SimplaMarket\KeyCRM\Entities\KeyCRMOrderEntity;
use Okay\Modules\SimplaMarket\KeyCRM\Helpers\RefererCRMHelper;

class FrontendExtender implements ExtensionInterface
{
    /* EntityFactory $entityFactory */
    private $entityFactory;

    /* KeyCRMHelper $keyCRMHelper */
    private $keyCRMHelper;

    /* KeyCRMEntity $KeyCRMEntity */
    private $KeyCRMEntity;

    /* KeyCRMOrderEntity $KeyCRMOrderEntity */
    private $KeyCRMOrderEntity;

    /* RefererCRMHelper $refererCRMHelper */
    private $refererCRMHelper;

    /* OrderStatusEntity $OrderStatusEntity */
    private $OrderStatusEntity;

    /* Settings $settings */
    private $settings;

    private static $userReferer;

    public function __construct(
        EntityFactory    $entityFactory,
        KeyCRMHelper     $keyCRMHelper,
        RefererCRMHelper $refererCRMHelper,
        Settings         $settings
    ){
        $this->entityFactory      = $entityFactory;
        $this->keyCRMHelper       = $keyCRMHelper;
        $this->refererCRMHelper   = $refererCRMHelper;
        $this->settings           = $settings;

        $this->KeyCRMEntity       = $entityFactory->get(KeyCRMEntity::class);
        $this->KeyCRMOrderEntity  = $entityFactory->get(KeyCRMOrderEntity::class);
        $this->OrderStatusEntity  = $entityFactory->get(OrderStatusEntity::class);
    }

    public function sendCartOrder($null,$order)
    {
        if (!empty($order)) {
            $orderMain = $order;
            $statusOrderAccess  = $this->OrderStatusEntity->findOne(['id' => $orderMain->status_id]);
            $orderKeyCRMO       = $this->KeyCRMOrderEntity->findOne(['source_uuid'=>$orderMain->id]);      //  проверяем отправлялся заказ уже,  source_uuid - id заказа в OkayCMS
            $RemoteKeyCRMROrder = $this->keyCRMHelper->getCRMOrder($order->id); //  узнаем о заказе в KeyCRM

            //  чтобы старые заказы не передавались ограничиваем срабатывание логики 48 часами
            if (strtotime($order->date) > (time() - 2*24*60*60)) {
                if (empty($orderKeyCRMO)
                    && !empty($RemoteKeyCRMROrder)
                ) {
                    $this->keyCRMHelper->addRecordToKeyCRMOrder($RemoteKeyCRMROrder);
                    $orderKeyCRMO = $this->KeyCRMOrderEntity->findOne(['source_uuid' => $orderMain->id]);      //  проверяем отправлялся заказ уже,  source_uuid - id заказа в OkayCMS
                }

                if (!empty($orderMain)
                    && !empty($statusOrderAccess) && !empty($statusOrderAccess->sendStatusCRM) && ($statusOrderAccess->sendStatusCRM == 1)
                ) {     //  проверяем нужно ли с этим статусом отправлять
                    if (!empty($orderKeyCRMO)           //  отправлялся уже
                        && !empty($RemoteKeyCRMROrder)  // есть в CRM
                    ) { //  если уже отправляли заказ и он переведен в статус - Оплачен то обновляем в CRM
                        $this->keyCRMHelper->OrderRequestPUT($orderMain);
                    } else if (empty($RemoteKeyCRMROrder)
                    ) { //  иначе создаем новый если данный статус выбран как - Отправлять в CRM
                        $sendOrder = $this->keyCRMHelper->OrderRequest($orderMain);
                    }
                }
            }
        }

        return ExtenderFacade::execute(__METHOD__, $order, func_get_args());
    }

    //  необходимо еще обрабатывать изменение/сохранение заказов
    public function markedPaid($null, $ids, $state)
    {
        if (!empty($state)
            && !empty($ids)
            && is_array($ids)
            && !empty($id = reset($ids))
        ) {
            $OrdersEntity = $this->entityFactory->get(OrdersEntity::class);
            $order = $OrdersEntity->findOne(['id' => $id]);

            //  ищем в отправленных в CRM есть ли заказ
            $KeyCRMOrderEntity = $this->entityFactory->get(KeyCRMOrderEntity::class);
            $KeyCRMOrder = $KeyCRMOrderEntity->findOne(['source_uuid' => $id]);       //  source_uuid - id заказа в OkayCMS

            //  если не найден id в CRM то прекращаем
            if (!empty($KeyCRMOrder)
                && !empty($KeyCRMOrder->idCRM)
                && !empty($order)
                && !empty($order->payment_method_id)
            ) {
                //  узнаем номер заказа в CRM
                $orderCrmID = $KeyCRMOrder->idCRM;

                //  подготавливаем и собираем данные  о оплате заказа
                $part = "order/" . $orderCrmID . "?include=payments";  //  Отримання сутності замовлення за ідентифікатором з KeyCRM
                $getOrderPaymant = $this->keyCRMHelper->curlFunctions($part, 'GET');

                //  Оновлення оплати для існуючого замовлення
                if (!empty($getOrderPaymant)
                    && !empty($currentCRMPaymentId = intval((!empty($getOrderPaymant) && !empty($getOrderPaymant->payments) && is_array($getOrderPaymant->payments) && !empty($lastType = array_slice($getOrderPaymant->payments, -1)[0]) && !empty($lastType->id))
                        ? $lastType->id : null))   //  пришли из CRM данные с из способа оплаты для этого заказа
                    && !empty($state)  //  если заказ на сайте оплачен
                ) {
                    $this->keyCRMHelper->setPaymentToCRM($orderCrmID, $currentCRMPaymentId, 'paid', 'paid order');
                }
            }
        }

        ExtenderFacade::execute(__METHOD__, null, func_get_args());
    }

    //  дополняем данные UTM метки
    public function prepareAdd($order, $orderOld)
    {
        $order->referer_term = (!empty($order->referer_source) ? $order->referer_source : '');

        if ($userReferer = $this->refererCRMHelper->getCrmUserReferer()) {
            $order->referer_term = ((!empty($userReferer['searchTerm'])) ? $userReferer['searchTerm'] : '');
            $order->utm_medium   = !empty($userReferer['utm_medium']) ? $userReferer['utm_medium'] : null;        //  Тип интеграции
            $order->utm_source   = !empty($userReferer['utm_source']) ? $userReferer['utm_source'] : (!empty($order->referer_source) ? $order->referer_source : '');        //  Тип трафика
            $order->utm_campaign = !empty($userReferer['utm_campaign']) ? $userReferer['utm_campaign'] : null;    //  Название площадки / Идентификатор кампании
            $order->utm_term     = !empty($userReferer['utm_term']) ? $userReferer['utm_term'] : null;            //  Необязательный параметр. Можно использовать для дополнительной информации: например, указать размер площадки
            $order->utm_content  = !empty($userReferer['utm_content']) ? $userReferer['utm_content'] : null;      //  Название контента
        }

        return ExtenderFacade::execute(__METHOD__, $order, func_get_args());
    }

}