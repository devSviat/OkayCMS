<?php

namespace Okay\Modules\SimplaMarket\KeyCRM\Helpers;

use Exception;
use Okay\Core\Config;
use Okay\Core\Database;
use Okay\Core\EntityFactory;
use Okay\Core\Image;
use Okay\Core\Modules\Extender\ExtenderFacade;
use Okay\Core\QueryFactory;
use Okay\Core\Request;
use Okay\Core\Settings;
use Okay\Entities\DeliveriesEntity;
use Okay\Entities\ImagesEntity;
use Okay\Entities\ModulesEntity;
use Okay\Entities\OrdersEntity;
use Okay\Entities\OrderStatusEntity;
use Okay\Entities\PaymentsEntity;
use Okay\Entities\ProductsEntity;
use Okay\Entities\PurchasesEntity;
use Okay\Entities\VariantsEntity;
use Okay\Modules\OkayCMS\DeliveryFields\Entities\DeliveryFieldsEntity;
use Okay\Modules\OkayCMS\DeliveryFields\Entities\DeliveryFieldsValuesEntity;
use Okay\Modules\OkayCMS\DeliveryFields\Init\Init;
use Okay\Modules\OkayCMS\NovaposhtaCost\Entities\NPCitiesEntity;
use Okay\Modules\OkayCMS\NovaposhtaCost\Entities\NPCostDeliveryDataEntity;
use Okay\Modules\OkayCMS\NovaposhtaCost\Entities\NPWarehousesEntity;
use Okay\Modules\SimplaMarket\KeyCRM\Entities\KeyCRMEntity;
use Okay\Modules\SimplaMarket\KeyCRM\Entities\KeyCRMDeliveryServicesEntity;
use Okay\Modules\SimplaMarket\KeyCRM\Entities\KeyCRMOrderEntity;
use Okay\Modules\SimplaMarket\KeyCRM\Entities\KeyCRMSourceEntity;
use Okay\Modules\SimplaMarket\KeyCRM\Entities\KeyCRMStatusesEntity;
use Okay\Modules\SimplaMarket\KeyCRM\Entities\KeyCRMPaymentMethodsEntity;
use Psr\Log\LoggerInterface;

class KeyCRMHelper
{
    /* EntityFactory $entityFactory */
    public $entityFactory;

    /* Config $config */
    public $config;

    /* Image $image */
    public $image;

    /* Request $request */
    public $request;

    /* Settings $settings */
    public $settings;

    /* LoggerInterface $logger */
    public $logger;

    /* OrdersEntity $ordersEntity */
    public $ordersEntity;

    /* OrderStatusEntity $OrderStatusEntity */
    public $OrderStatusEntity;

    /* QueryFactory $queryFactory */
    public $queryFactory;

    /* Database $db */
    public $db;

    /** @var KeyCRMEntity $keyCRMEntity */
    public $keyCRMEntity;

    public $apiKey;
    public $paymentList = [];

    public function __construct(
        EntityFactory   $entityFactory,
        Config          $config,
        Image           $image,
        Request         $request,
        Settings        $settings,
        LoggerInterface $logger,
        QueryFactory    $queryFactory,
        Database        $db
    )
    {
        $this->entityFactory = $entityFactory;
        $this->config        = $config;
        $this->image         = $image;
        $this->request       = $request;
        $this->settings      = $settings;
        $this->logger        = $logger;
        $this->queryFactory  = $queryFactory;
        $this->db            = $db;
        $this->ordersEntity       = $entityFactory->get(OrdersEntity::class);
        $this->OrderStatusEntity  = $entityFactory->get(OrderStatusEntity::class);
        $this->keyCRMEntity       = $this->entityFactory->get(KeyCRMEntity::class);
    }

    public function testKeyAPIHelper($apiKey)
    {
        $error = new \stdClass();
        //  отправка запроса
        $error = $this->curlFunctions('order', 'GET');

        if (!empty($error->current_page)) {
            $error = '';
        }

        return ExtenderFacade::execute(__METHOD__, $error, func_get_args());
    }

    public function sourseListHelper($apiKey)
    {
        $sourseListHelper = new \stdClass();
        $sourseList = new \stdClass();
        $part = 'order/source?limit=50';
        $method = 'GET';
        //  отправка запроса
        $sourseListHelper = $this->curlFunctions($part, $method);

        if (!empty($sourseListHelper->current_page)) {

            $KeyCRMSourceEntity = $this->entityFactory->get(KeyCRMSourceEntity::class);
            $allSourseLists = $KeyCRMSourceEntity->find();

            if (!empty($allSourseLists)) {
                $sourseListsCurls = $sourseListHelper->data;

                foreach ($sourseListsCurls as $sourseListsCurl) {
                    $sourseList->idCRM = $sourseListsCurl->id;
                    $sourseList->name = $sourseListsCurl->name;
                    $sourseList->alias = $sourseListsCurl->alias;
                    $sourseList->driver = $sourseListsCurl->driver;
                    $sourseList->source_name = $sourseListsCurl->source_name;
                    $sourseList->source_uuid = $sourseListsCurl->source_uuid;
                    $sourseList->currency_code = $sourseListsCurl->currency_code;
                    $sourseList->source = "source";
                    $sourseList->expense_type_id = $sourseListsCurl->expense_type_id;
                    $sourseList->with_expenses = $sourseListsCurl->with_expenses;
                    $sourseList->created_at = $sourseListsCurl->created_at;
                    $sourseList->updated_at = $sourseListsCurl->updated_at;
                    $sourseList->deleted_at = $sourseListsCurl->deleted_at;
                    foreach ($allSourseLists as $key => $allSourseList) {
                        if ($sourseList->name == $allSourseList->name) {
                            $arraySearch = $key;
                            $KeyCRMSourceEntity->update($arraySearch, $sourseList);
                        }
                    }
                }
            } else {

                $sourseListsCurls = $sourseListHelper->data;

                foreach ($sourseListsCurls as $sourseListsCurl) {
                    $sourseList->idCRM = $sourseListsCurl->id;
                    $sourseList->name = $sourseListsCurl->name;
                    $sourseList->alias = $sourseListsCurl->alias;
                    $sourseList->driver = $sourseListsCurl->driver;
                    $sourseList->source_name = $sourseListsCurl->source_name;
                    $sourseList->source_uuid = $sourseListsCurl->source_uuid;
                    $sourseList->currency_code = $sourseListsCurl->currency_code;
                    $sourseList->source = "source";
                    $sourseList->expense_type_id = $sourseListsCurl->expense_type_id;
                    $sourseList->with_expenses = $sourseListsCurl->with_expenses;
                    $sourseList->created_at = $sourseListsCurl->created_at;
                    $sourseList->updated_at = $sourseListsCurl->updated_at;
                    $KeyCRMSourceEntity->add($sourseList);
                }
            }
        }

        return ExtenderFacade::execute(__METHOD__, $sourseListHelper, func_get_args());
    }

    //  получаем из CRM список всех статусов
    public function getOrderStatusList($apiKey) {
        $statusListHelper = [];
        $part = 'order/status?limit=50';
        $method = 'GET';
        //  отправка запроса
        $statusListHelper = $this->curlFunctions($part, $method);

        return ExtenderFacade::execute(__METHOD__, $statusListHelper, func_get_args());
    }

    public function statusListHelper($apiKey)
    {
        $statusListHelper = $this->getOrderStatusList($apiKey);

        if (!empty($statusListHelper->current_page)) {

            $KeyCRMStatusEntity = $this->entityFactory->get(KeyCRMStatusesEntity::class);
            $allStatusLists = $KeyCRMStatusEntity->find();

            if (!empty($allStatusLists)) {
                $statusListsCurls = $statusListHelper->data;

                foreach ($statusListsCurls as $statusListsCurl) {
                    $statusList = new \stdClass();
                    $statusList->idCRM = $statusListsCurl->id;
                    $statusList->name = $statusListsCurl->name;
                    $statusList->alias = $statusListsCurl->alias;
                    $statusList->is_active = $statusListsCurl->is_active;
                    $statusList->group_id = $statusListsCurl->group_id;
                    $statusList->is_closing_order = $statusListsCurl->is_closing_order;
                    $statusList->is_reserved = $statusListsCurl->is_reserved;
                    $statusList->expiration_period = $statusListsCurl->expiration_period;
                    $statusList->created_at = $statusListsCurl->created_at;
                    $statusList->updated_at = $statusListsCurl->updated_at;

                    foreach ($allStatusLists as $key => $allStatusList) {
                        if ($statusList->name == $allStatusList->name) {
                            $arraySearch = $key;
                            $KeyCRMStatusEntity->update($arraySearch, $statusList);
                        }
                    }
                }
            } else {
                $statusListsCurls = $statusListHelper->data;

                foreach ($statusListsCurls as $statusListsCurl) {
                    $statusList = new \stdClass();
                    $statusList->idCRM = $statusListsCurl->id;
                    $statusList->name = $statusListsCurl->name;
                    $statusList->alias = $statusListsCurl->alias;
                    $statusList->is_active = $statusListsCurl->is_active;
                    $statusList->group_id = $statusListsCurl->group_id;
                    $statusList->is_closing_order = $statusListsCurl->is_closing_order;
                    $statusList->is_reserved = $statusListsCurl->is_reserved;
                    $statusList->expiration_period = $statusListsCurl->expiration_period;
                    $statusList->created_at = $statusListsCurl->created_at;
                    $statusList->updated_at = $statusListsCurl->updated_at;
                    $KeyCRMStatusEntity->add($statusList);
                }
            }
        }

        return ExtenderFacade::execute(__METHOD__, $statusListHelper, func_get_args());
    }

    /** Получаем API ключ указанный в админпанели в настройках модуля
     * @return false|object|string|null
     */
    public function getApiKey() {
        if (empty($this->apiKey)) {
            $ApiKey = $this->keyCRMEntity->findOne(['key' => 'ApiKey']);

            $this->apiKey = !empty($ApiKey) ? $ApiKey : null;
        }

        return !empty($this->apiKey) ? $this->apiKey : null;
    }

    /** Получаем номер источника на который сайт отправляет данные в CRM
     * @return null
     */
    public function getCRMSourceId() {
        $sourceId = null;

        $moduleSettings = $this->keyCRMEntity->findOne(['key' => 'sourseCRM']);
        if (!empty($moduleSettings) && !empty($moduleSettings->value)) {
            $sourceId = (int)$moduleSettings->value;
        }

        return $sourceId;
    }

    public function getPaymentListHelper()
    {
        $apiKey = $this->getApiKey();

        if (empty($this->paymentList)) {
            $this->paymentList = $this->paymentListHelper($apiKey);
        }

        return $this->paymentList;
    }

    public function paymentListHelper($apiKey)
    {
        $paymentListHelper = new \stdClass();
        $paymentList = new \stdClass();
        $part = 'order/payment-method?limit=50';
        $method = 'GET';
        //  отправка запроса
        $paymentListHelper = $this->curlFunctions($part, $method);

        if (!empty($paymentListHelper->current_page)) {

            $KeyCRMPaymentEntity = $this->entityFactory->get(KeyCRMPaymentMethodsEntity::class);
            $allPaymentLists = $KeyCRMPaymentEntity->find();

            if (!empty($allPaymentLists)) {
                $paymentListsCurls = $paymentListHelper->data;

                foreach ($paymentListsCurls as $paymentListsCurl) {

                    $paymentList->idCRM = $paymentListsCurl->id;
                    $paymentList->name = $paymentListsCurl->name;
                    $paymentList->alias = $paymentListsCurl->alias;
                    $paymentList->is_active = $paymentListsCurl->is_active;

                    foreach ($allPaymentLists as $key => $allPaymentList) {
                        if ($paymentList->name == $allPaymentList->name) {
                            $arraySearch = $key;
                            $KeyCRMPaymentEntity->update($arraySearch, $paymentList);
                        }
                    }
                }
            } else {
                $paymentListsCurls = $paymentListHelper->data;
                foreach ($paymentListsCurls as $paymentListsCurl) {

                    $paymentList->idCRM = $paymentListsCurl->id;
                    $paymentList->name = $paymentListsCurl->name;
                    $paymentList->alias = $paymentListsCurl->alias;
                    $paymentList->is_active = $paymentListsCurl->is_active;

                    $KeyCRMPaymentEntity->add($paymentList);
                }
            }
        }

        return ExtenderFacade::execute(__METHOD__, $paymentListHelper, func_get_args());
    }

    /** Получение статусов доставки и сохранение их в БД
     * @param $apiKey
     * @return mixed|\stdClass
     * @throws \Exception
     */
    public function deliveryListHelper($apiKey)
    {
        $deliveryListHelper = new \stdClass();
        $deliveryList = new \stdClass();
        $part = 'order/delivery-service?limit=50';
        $method = 'GET';
        //  отправка запроса
        $deliveryListHelper = $this->curlFunctions($part, $method);

        if (!empty($deliveryListHelper->current_page)) {

            $KeyCRMDeliveryEntity = $this->entityFactory->get(KeyCRMDeliveryServicesEntity::class);
            $allDeliveryLists = $KeyCRMDeliveryEntity->find();

            if (!empty($allDeliveryLists)) {
                $deliveryListsCurls = $deliveryListHelper->data;

                foreach ($deliveryListsCurls as $deliveryListsCurl) {

                    $deliveryList->idCRM = $deliveryListsCurl->id;
                    $deliveryList->name = $deliveryListsCurl->name;
                    $deliveryList->source_name = $deliveryListsCurl->source_name;
                    $deliveryList->alias = $deliveryListsCurl->alias;

                    foreach ($allDeliveryLists as $key => $allDeliveryList) {
                        if ($deliveryList->name == $allDeliveryList->name) {
                            $arraySearch = $key;
                            $KeyCRMDeliveryEntity->update($arraySearch, $deliveryList);
                        }
                    }
                }
            } else {
                $deliveryListsCurls = $deliveryListHelper->data;

                foreach ($deliveryListsCurls as $deliveryListsCurl) {

                    $deliveryList->idCRM = $deliveryListsCurl->id;
                    $deliveryList->name = $deliveryListsCurl->name;
                    $deliveryList->source_name = $deliveryListsCurl->source_name;
                    $deliveryList->alias = $deliveryListsCurl->alias;

                    $KeyCRMDeliveryEntity->add($deliveryList);
                }
            }
        }

        return ExtenderFacade::execute(__METHOD__, $deliveryListHelper, func_get_args());
    }

    //  найти по номеру
    public function getCRMOrder($orderOkayId, $include = '')
    {
        if (empty($orderOkayId)) {
            return ExtenderFacade::execute(__METHOD__, null, func_get_args());
        }

        $sourceId = $this->getCRMSourceId();

        if (empty($sourceId)) {
            return ExtenderFacade::execute(__METHOD__, null, func_get_args());
        }

        $result = null;
        $part = 'order?filter[source_uuid]='. trim($orderOkayId) . "&filter[source_id]=" . $sourceId;

        if (!empty($include)) {
            $part .= '&include=' . $include;
        }
        //  отправка запроса
        $resultData = $this->curlFunctions($part, 'GET');

        if (!empty($resultData)
            && !empty($resultData->data)
        ) {
            foreach ($resultData->data as $order) {
                if (!empty($order->source_uuid)
                    && ($order->source_uuid == intval(trim($orderOkayId)))
                ) {
                    //  возвращаем заказ
                    return ExtenderFacade::execute(__METHOD__, $order, func_get_args());
                }
            }
        } elseif ( (!empty($resultData->data) && empty($result = reset($resultData->data)))
                    || (!empty($result) && !empty($result->message) && (stripos($result->message, 'not found') !== false))
        ) {    //  заказе нет
            return ExtenderFacade::execute(__METHOD__, null, func_get_args());
        }

        return ExtenderFacade::execute(__METHOD__, null, func_get_args());
    }

    //  отправка нового заказа
    public function OrderRequest($orderSend, $checkRemoteOrder = 0, $findRemoteOrder = null)
    {
        if (empty($orderSend) || empty($orderSend->id)) {
            return ExtenderFacade::execute(__METHOD__, false, func_get_args());
        }

        if (($checkRemoteOrder == 1)   //  если есть параметр для проверки
            && empty($findRemoteOrder)) {
            $findRemoteOrder = $this->getCRMOrder($orderSend->id);
        }

        //  проверяем есть ли заказ в CRM
        if (($checkRemoteOrder == 1)   //  если есть параметр для проверки
            && !empty($findRemoteOrder)     //  и в CRM есть такой заказ
        ) { //  если заказ в CRM есть
            //  то записываем себе запись что он уже отправлен
            $this->addRecordToKeyCRMOrder($findRemoteOrder);

            //  обновляем заказа
            $this->OrderRequestPUT($orderSend, 0);

            return ExtenderFacade::execute(__METHOD__, true, func_get_args());
        }

        $sendOrder = new \stdClass();

        $KeyCRMEntity               = $this->entityFactory->get(KeyCRMEntity::class);
        $KeyCRMPaymentMethodsEntity = $this->entityFactory->get(KeyCRMPaymentMethodsEntity::class);
        $PurchasesEntity            = $this->entityFactory->get(PurchasesEntity::class);
        $ProductsEntity             = $this->entityFactory->get(ProductsEntity::class);
        $VariantsEntity             = $this->entityFactory->get(VariantsEntity::class);
        $ImagesEntity               = $this->entityFactory->get(ImagesEntity::class);
        $NPCostDeliveryDataEntity   = $this->entityFactory->get(NPCostDeliveryDataEntity::class);
        $NPCitiesEntity             = $this->entityFactory->get(NPCitiesEntity::class);
        $NPWarehousesEntity         = $this->entityFactory->get(NPWarehousesEntity::class);
        /** PaymentsEntity $PaymentsEntity */
        $PaymentsEntity             = $this->entityFactory->get(PaymentsEntity::class);
        /** DeliveriesEntity $DeliveriesEntity */
        $DeliveriesEntity             = $this->entityFactory->get(DeliveriesEntity::class);
        /** OrderStatusEntity $OrderStatusEntity */
        $OrderStatusEntity          = $this->entityFactory->get(OrderStatusEntity::class);

        $purchasesSends = $PurchasesEntity->find(['order_id' => $orderSend->id]);
        foreach ($purchasesSends as $key => $purchasesSend) {
            $productSend = $ProductsEntity->findOne(['id' => $purchasesSend->product_id]);
            if (!empty($productSend->id)) {
                $variantSend = $VariantsEntity->findOne(['product_id' => $productSend->id]);
            } else {
                $variantSend = null;
            }
            $purchasesSend->product[$key] = $productSend;
            $purchasesSend->variant[$key] = $variantSend;
        }

        $buyer = new \stdClass();
        $buyer->full_name = $orderSend->name . (!empty($orderSend->last_name) ? (' ' . $orderSend->last_name) : '');
        $buyer->email = $orderSend->email;
        $buyer->phone = $orderSend->phone;
        $errorText = [];
        //  если данные обязательных полей пустые, то нет возможности отправлять заказ в CRM
        if (empty($buyer->email)
             && empty($buyer->phone)
        ) {
            if (empty($buyer->email)) {
                $errorText[] = 'empty buyer email';
            }
            if (empty($buyer->phone)) {
                $errorText[] = 'empty buyer phone';
            }

            if (!empty($orderSend->id) && !empty($errorText)) {
                $this->ordersEntity->update($orderSend->id, ['error_crm' => implode(', ',$errorText)]);
            }

            return ExtenderFacade::execute(__METHOD__, false, func_get_args());
        }

        $sendOrderStatusId = null;

        $OrderStatusesFind = $OrderStatusEntity->mappedBy('id')->cols(['id', 'idCRM', 'sendStatusCRM'])->find([]);
        if (!empty($OrderStatusesFind)          //  получили из БД данные статусов
            && !empty($orderSend->status_id)    //  получили из БД данные статусов
            && !empty($OrderStatusesFind[$orderSend->status_id]) //  есть ассоциативный id из CRM для id статуса на сайте
            && !empty($idCRM = intval($OrderStatusesFind[$orderSend->status_id]->idCRM)) //  есть ассоциативный id из CRM для id статуса на сайте
            && ($idCRM != '') //  статус не пуст
        ) {
            //  собираем данные, обновляем статус заказа
            $sendOrderStatusId = $idCRM;
        }

        //  добавляем статус
        if (!empty($sendOrderStatusId)) {
            $sendOrder->status_id = $sendOrderStatusId;
        }

        $address = '';
        $shipping = new \stdClass();
        $deliveryMethod = $KeyCRMEntity->findOne(['value' => $orderSend->delivery_id, 'key' => 'deliveryCRM']);
        if (!empty($deliveryMethod)) {
            $delivery = $deliveryMethod;
            $shipping->delivery_service_id = intval($delivery->value2);
        }

        //NP
        $shippingNP = $NPCostDeliveryDataEntity->findOne(['order_id' =>$orderSend->id]);

        if(!empty($shippingNP->warehouse_id)){
            $NPciti = $NPCitiesEntity->findOne(['ref'=>$shippingNP->city_id]);
            $NPadress = $NPWarehousesEntity->findOne(['ref'=>$shippingNP->warehouse_id]);
        }
        $redelivery = null;
        if (!empty($shippingNP)){
            $redelivery = $shippingNP->redelivery;
            $address = [];
            if ($shippingNP->city_name){
                $shipping->shipping_address_city = $shippingNP->city_name;
                $address[] = $shippingNP->city_name;
            } else {
                if (!empty($NPciti)){
                    $shipping->shipping_address_city = $NPciti->name;
                    $address[] = $NPciti->name;
                } else {
                    $shipping->shipping_address_city = '';
                }
            }

            if ($shippingNP->area_name){
                $shipping->shipping_address_region = $shippingNP->area_name;
                $address[] = $shippingNP->area_name;
            } else {
                $shipping->shipping_address_region = ' ';
            }

            if ($shippingNP->street){
                $allAddress =  $shippingNP->street . ' ' . $shippingNP->house . ' ' . $shippingNP->apartment;
                $shipping->shipping_receive_point = $allAddress;
                $address[] = $allAddress;
            } else {
                if (!empty($NPadress)){
                    $shipping->shipping_receive_point = $NPadress->name;
                    $address[] = $NPadress->name;
                } else {
                    $shipping->shipping_receive_point = '';
                }
            }
        } else {
            $shipping->shipping_address_city = '';
            $shipping->shipping_address_region = ' ';
            $shipping->shipping_secondary_line = '';
        }
        //NP*
        $shipping->shipping_address_country = 'Ukraine';
        $shipping->shipping_address_zip = ' ';
        if (empty($shipping->shipping_receive_point)
            && !empty((int)$orderSend->id)
            && !empty($delivery_id = (int)$orderSend->delivery_id)
        ) {
            if (class_exists(\Okay\Modules\OkayCMS\DeliveryFields\Helpers\DeliveryFieldsHelper::class)
                && !empty($findModule = $this->entityFactory->get(ModulesEntity::class)->findOne(['vendor' => 'OkayCMS', 'module_name' => 'DeliveryFields']))
                && ($findModule->enabled == 1)
            ) {
                $select = $this->queryFactory->newSelect();
                $select->cols([
                    DeliveryFieldsValuesEntity::getTableAlias().'.field_id AS field_id',
                    DeliveryFieldsValuesEntity::getTableAlias().'.order_id AS order_id',
                    DeliveryFieldsValuesEntity::getTableAlias().'.value AS value',
                    DeliveryFieldsEntity::getTableAlias().'.name AS name',
                    DeliveryFieldsEntity::getTableAlias().'.position AS position',
                ])
                    ->from(DeliveryFieldsEntity::getTable() . ' AS ' . DeliveryFieldsEntity::getTableAlias())

                    ->innerJoin(
                        Init::FIELD_DELIVERY_RELATION_TABLE . ' AS dfr',
                        DeliveryFieldsEntity::getTableAlias().'.id = dfr.field_id AND dfr.delivery_id IN (?)',
                        [
                            (array)$delivery_id,
                        ])
                    ->leftJoin(DeliveryFieldsValuesEntity::getTable() . ' AS ' . DeliveryFieldsValuesEntity::getTableAlias(),
                        DeliveryFieldsValuesEntity::getTableAlias().'.field_id = dfr.field_id')

                    ->where(DeliveryFieldsValuesEntity::getTableAlias().'.order_id = :order_id')->bindValue('order_id', (int)$orderSend->id)
                    ->orderBy(['position ASC'])
                ;

                $this->db->query($select);
                $fields = $this->db->results();

                if (!empty($fields)) {
                    $address = [];
                    foreach ($fields as $field) {
                        if (!empty($field->name)) {
                            $address[] = ' | ' . $field->name . ": " . $field->value;
                        }
                    }
                }
            }
            if (!empty($address)){
                if (is_array($address)) {
                    $address = (string)(implode('; ', $address));
                }
            } else {
                $address = '';
            }
            $shipping->shipping_receive_point = $address;
        }
        $shipping->recipient_full_name = ' ';
        $shipping->recipient_phone = ' ';

        $marketing = new \stdClass();
        $marketing->utm_source   = (!empty($orderSend->utm_source)) ? $orderSend->utm_source : ' ';
        $marketing->utm_medium   = (!empty($orderSend->utm_medium)) ? $orderSend->utm_medium : ' ';
        $marketing->utm_campaign = (!empty($orderSend->utm_campaign)) ? $orderSend->utm_campaign : ' ';
        $marketing->utm_term     = (!empty($orderSend->utm_term)) ? $orderSend->utm_term : ' ';
        $marketing->utm_content  = (!empty($orderSend->utm_content)) ? $orderSend->utm_content : ' ';

        $products = array();
        $total_price_undiscounted = 0;
        foreach ($purchasesSends as $key => $purchases) {
            $products[$key] = new \stdClass();
            $products[$key]->sku = $purchases->sku;
            $products[$key]->price = floatval($purchases->price);
            if ($purchases->price != $purchases->undiscounted_price) {
                $products[$key]->discount_amount = floatval($purchases->undiscounted_price) - floatval($purchases->price);
            }
            //image
            $productImages = $ImagesEntity->findOne(['product_id' => $purchases->product_id]);
            if (!empty($productImages) && !empty($productImages->filename)) {
                $im = $this->image->getResizeModifier($productImages->filename, 600, 800);
            } else {
                $im = '';
            }
            /*/image*/

            $products[$key]->quantity = intval($purchases->amount);
            $products[$key]->name = $purchases->product_name;
            $products[$key]->picture = $im;
            $products[$key]->properties[0] = new \stdClass();
            $products[$key]->properties[0]->name = $purchases->product_name;
            $products[$key]->properties[0]->value = $purchases->variant_name;
        }

        $payments = array();
        $paymentMethodId = null;
        if (!empty($orderSend->payment_method_id)) {
            $paymentMethodId = $KeyCRMEntity->findOne(['value' => $orderSend->payment_method_id, 'key' => 'paymentCRM']);
        }
        //CMS payment
        $paymentsCMS = new \stdClass();
        if (!empty($paymentMethodId)) {
            $paymentMethodName = $KeyCRMPaymentMethodsEntity->findOne(['idCRM' => $paymentMethodId->value2]);
            $paymentsCMS = $PaymentsEntity->findOne(['id'=>$paymentMethodId->value]);

            $payments[0] = new \stdClass();
            $payments[0]->payment_method_id = intval($paymentMethodId->value2);
            $payments[0]->payment_method = !empty($paymentMethodName->name) ? $paymentMethodName->name : '';

            $amount = floatval($orderSend->total_price);

            $payments[0]->amount = $amount;
            $payments[0]->description = 'Платеж';
            $payments[0]->payment_date = date('Y-m-d H:i:s');
            $paid = 'not_paid';
            if ($orderSend->paid != 0) {
                $paid = 'paid';
            }
            $payments[0]->status = $paid;
        }

        $sendOrder->source_id = $this->getCRMSourceId();
        $sendOrder->source_uuid = (string)$orderSend->id;
        $sendOrder->buyer_comment = $orderSend->comment;
        $sendOrder->manager_comment = $orderSend->note;
        $sendOrder->promocode = ' ';

        //  добавляем статус
        if (!empty($sendOrderStatusId)) {
            $sendOrder->status_id = $sendOrderStatusId;
        }

        //  передача скидок
        $discountAmountOrder = null;

        $settings_keycrm__checkbox_disable_send_delivery_price = $this->settings->get('keycrm__checkbox_disable_send_delivery_price');
        $settings_keycrm__checkbox_disable_send_separate_delivery_price = $this->settings->get('keycrm__checkbox_disable_send_separate_delivery_price');

        //  передаем общую скидку из заказа
        if (!empty($orderSend->total_price) && !empty($orderSend->undiscounted_total_price) && $orderSend->total_price != $orderSend->undiscounted_total_price) {

            if ($settings_keycrm__checkbox_disable_send_separate_delivery_price == 0) {    //  если клиент отметил передавать данные
                $discountAmountOrder += floatval($orderSend->undiscounted_total_price - $orderSend->total_price) + floatval($orderSend->delivery_price);
            } else {
                $discountAmountOrder += floatval($orderSend->undiscounted_total_price - floatval($orderSend->total_price));
            }
        }

        //  передача стоимости доставки и в зависимости от некоторых пареметров и модифицируем скидку в зависимости от стоимости доставки
        if (!empty($orderSend->delivery_price)  //  если стоимость доставки есть
            && !empty($orderSend->delivery_id)  //  если есть id доставки
            && !empty($delivery = $DeliveriesEntity->findOne(['id' => $orderSend->delivery_id]))    //  если найдена такая доставка
        ) {
            if (($orderSend->separate_delivery == 1)    // стоимость доставки оплачивается отдельно, по уполчанию ничего не передаем
            ) {
                if ($settings_keycrm__checkbox_disable_send_separate_delivery_price == 1) {    //  если клиент отметил передавать данные
                    $discountAmountOrder       += floatval($orderSend->delivery_price);   //  передаем стоимость доставки как скидку
                    $sendOrder->shipping_price = floatval($orderSend->delivery_price);    //  передаем стоимость доставки
                }

            } elseif (($orderSend->separate_delivery == 0)  //  стоимость доставки включается в заказ но большой заказ и случается бесплатная доставка
                && (($total_price_undiscounted - $discountAmountOrder) > $delivery->free_from)  //  общая стоимость товаров минус скидки > суммы после которой бесплатная доставка
            ) {    // цена доставки и доставка бесплатна т.к. стоимость заказа больше величины бесплатной доставки,

                if ($settings_keycrm__checkbox_disable_send_delivery_price == 0) {  //   по умолчанию передается, не передается если клиент галочкой отключил
                    $discountAmountOrder       += floatval($orderSend->delivery_price);   //  передаем стоимость доставки как скидку
                    $sendOrder->shipping_price = floatval($orderSend->delivery_price);    //  передаем стоимость доставки
                }

            } elseif (!empty($orderSend->delivery_price)) { //  если обычный способ доставки включающийся в стоимость заказа
                $sendOrder->shipping_price      = floatval($orderSend->delivery_price);
            }

        } else
            //  передаем стоимость доставки при определенных условиях
            if (!empty($orderSend->delivery_price)                          //  стоимость доставки установлена
                && empty($redelivery)                                       //  не наложный платеж
                && (!empty($paymentsCMS) && $paymentsCMS->module != "null") //  не ручной способ оплаты
            ) {
                $sendOrder->shipping_price = floatval($orderSend->delivery_price);
        }

        //  передаем накомпенную скидку
        if (!empty($discountAmountOrder)) {
            $sendOrder->discount_amount = $discountAmountOrder;     //  включаем в скидку стоимость доставки
        }

        $sendOrder->wrap_price = ' ';
        $sendOrder->taxes = ' ';
        $sendOrder->ordered_at = ( !empty($orderSend->date) ? $orderSend->date : date('Y-m-d H:i:s'));
        $sendOrder->buyer = $buyer;
        if (!empty($shipping) && !empty($shipping->delivery_service_id)) {
            $sendOrder->shipping = $shipping;
        }
        $sendOrder->marketing = $marketing;
        $sendOrder->products = $products;
        if (!empty($paymentMethodId)) {
            $sendOrder->payments = $payments;
        }

        //  отправка нового заказа
        if (!empty($sendOrder)) {
            $answer = $this->curlFunctions('order', 'POST', $sendOrder);

            return ExtenderFacade::execute(__METHOD__, $answer, func_get_args());
        }

        return ExtenderFacade::execute(__METHOD__, false, func_get_args());
    }

    /*  Оновлення оплати для існуючого замовлення
        PUT /order/{orderId}/payment/{paymentId}
     * */
    public function setPaymentToCRM($orderCrmId, $currentCRMPaymentId, $status, $description = ''){

        if (!empty($orderCrmId)
            && !empty($currentCRMPaymentId)
            && !empty($status) && in_array($status, ['paid', 'refund', 'not_paid', 'canceled'])
        ) {
            //  set
            $part = "order/" . $orderCrmId . "/payment/" . $currentCRMPaymentId;
            $method = 'PUT';

            $sendOrder = new \stdClass();
            $sendOrder->status      = $status;
            $sendOrder->description = $description;

            $answerUpdatePayments = $this->curlFunctions($part, $method, $sendOrder);

            return $answerUpdatePayments;
        }

        return false;
    }

    /*  Додавання нової оплати для існуючого замовлення
     * POST /order/{orderId}/payment
     * {
     *   "payment_method_id": 2,
     *   "payment_method": "Apple Pay",
     *   "amount": 123.5,
     *   "status": "paid",
     *   "description": "Авансовий платіж",
     *   "payment_date": "2021-02-21 14:44:00"
     *  }
     * */
    public function addPaymentToCRM($orderCrmID, $orderOkayId, $order = null){

        //  получаем заказ, если не передали
        if (empty($order)) {
            $order = $this->ordersEntity->findOne(['id' => $orderOkayId]);
        }

        $answerUpdatePayments = null;
        $crmPaymentMethod = null;
        if (!empty($orderCrmID)
            && !empty($order)
            && !empty($orderOkayId)
            && !empty($status = ($order->paid == 1) ? 'paid' : 'not_paid')
            && in_array($status, ['paid', 'refund', 'not_paid', 'canceled'])
        ) {
            //  получаем инфо про способ оплаты
            if (!empty($order->payment_method_id)) {
                $KeyCRMEntity = $this->entityFactory->get(KeyCRMEntity::class);
                $paymentCRM   = $KeyCRMEntity->findOne(['value' => $order->payment_method_id, 'key' => 'paymentCRM']);
                if (!empty($paymentCRM) && !empty($paymentCRM->value2)) {
                    $crmPaymentMethod = $paymentCRM->value2;
                }
            }

            if (!empty($order->payment_method_id)) {
                /** PaymentsEntity $PaymentsEntity */
                $PaymentsEntity = $this->entityFactory->get(PaymentsEntity::class);
                $paymentsCMS = $PaymentsEntity->findOne(['id' => $order->payment_method_id]);
            }

            if (!empty($orderCrmID) && !empty($crmPaymentMethod)){
               $part = "order/" . $orderCrmID . "/payment";  //  Додавання нової оплати для існуючого замовлення в KeyCRM

               //  set
               $sendOrder = new \stdClass();
               $sendOrder->payment_method_id = $crmPaymentMethod;
               $sendOrder->payment_method = !empty($paymentsCMS->name) ? $paymentsCMS->name : ' ';
               $sendOrder->amount = $order->total_price;
               $sendOrder->status = $status;
               $sendOrder->description = (!empty($paymentsCMS->name) ? $paymentsCMS->name .'. ' : '') . ' Changed payment method.';
               $sendOrder->payment_date = date('Y-m-d H:i:s');

               $answerUpdatePayments = $this->curlFunctions($part, 'POST', $sendOrder);

               return $answerUpdatePayments;
           }
        }

        return false;
    }

    //  обновляем для текущего заказа его состояние оплаты и другие данные
    public function OrderRequestPUT($order, $checkRemoteOrder = 0, $findRemoteOrder = null)
    {
        $orderOkayId = $order->id;
        $trackingCode = null;
        $KeyCRMEntity      = $this->entityFactory->get(KeyCRMEntity::class);
        $KeyCRMOrderEntity = $this->entityFactory->get(KeyCRMOrderEntity::class);
        $answerUpdatePayments = null;

        //  ищем в отправленных в CRM есть ли заказ
        $KeyCRMOrder = $KeyCRMOrderEntity->findOne(['source_uuid' => $order->id]);       //  source_uuid - id заказа в OkayCMS
        $paymentCRM  = $KeyCRMEntity->findOne(['value' => $order->payment_method_id, 'key' => 'paymentCRM']);

        //  узнаем номер заказа в CRM
        $orderID = $KeyCRMOrder->idCRM;     //  номер заказа в CRM
        $orderCrmID = $orderID;
        //  если не найден id в CRM то прекращаем
        if (empty($orderID)) {
            return ExtenderFacade::execute(__METHOD__, null, func_get_args());
        }

        if (($checkRemoteOrder == 1)   //    если есть параметр для проверки
            && empty($findRemoteOrder)
        ) {
            $findRemoteOrder = $this->getCRMOrder($order->id);
        }

        //  проверяем есть ли заказ в CRM
        if (($checkRemoteOrder == 1)   //    если есть параметр для проверки
            && !empty($findRemoteOrder)     //  и в CRM есть такой заказ
            && empty($KeyCRMOrder)          //  и на сайте нет признака отправки
        ) { //  если заказ в CRM есть
            //  то записываем себе запись что он уже отправлен
            $this->addRecordToKeyCRMOrder($findRemoteOrder);
        }

        //  подготавливаем и собираем данные  о оплате заказа
        $part = "order/" . $orderCrmID . "?include=payments";  //  Отримання сутності замовлення за ідентифікатором з KeyCRM
        $getOrderPaymant = $this->curlFunctions($part, 'GET');

        $currentPaymentIdOnSite = (!empty($paymentCRM->value2)) ? intval($paymentCRM->value2) : null;

        $currentCRMPaymentId = intval((!empty($getOrderPaymant) && !empty($getOrderPaymant->payments) && is_array($getOrderPaymant->payments) && !empty($lastType = array_slice($getOrderPaymant->payments, -1)[0]) && !empty($lastType->id)) ? $lastType->id : null);

        $currentCRMPaymentMethodId = intval((!empty($getOrderPaymant) && !empty($getOrderPaymant->payments) && is_array($getOrderPaymant->payments) && !empty($lastType = array_slice($getOrderPaymant->payments, -1)[0]) && !empty($lastType->payment_method_id)) ? $lastType->payment_method_id : null);

        //  Если при обновлении определилось что способ оплаты изменили
        if (!empty($currentPaymentIdOnSite) && isset($currentCRMPaymentMethodId) && ($currentPaymentIdOnSite != $currentCRMPaymentMethodId)) {
            //  тогда отменяем текущий оплату заказа переводя его в статус - canceled
            $this->setPaymentToCRM($orderCrmID, $currentCRMPaymentId, 'canceled', 'canceled payment');

            //  И создаем новую оплату для заказа
            $this->addPaymentToCRM($orderCrmID, $orderOkayId, $order);
        }

        //  Оновлення оплати для існуючого замовлення
        if (!empty($getOrderPaymant) && !empty($currentCRMPaymentId)   //  пришли из CRM данные с из способа оплаты для этого заказа
            && ($order->paid == 1)  //  если заказ на сайте оплачен
        ) {
            $this->setPaymentToCRM($orderCrmID, $currentCRMPaymentId, 'paid', 'paid order');
        }

        //  Оновлення існуючого замовлення
        /** OrdersEntity $OrdersEntity */
        $OrdersEntity             = $this->entityFactory->get(OrdersEntity::class);
        /** OrderStatusEntity $OrderStatusEntity */
        $OrderStatusEntity        = $this->entityFactory->get(OrderStatusEntity::class);
        /** PurchasesEntity $PurchasesEntity */
        $PurchasesEntity          = $this->entityFactory->get(PurchasesEntity::class);
        /** ProductsEntity $ProductsEntity */
        $ProductsEntity           = $this->entityFactory->get(ProductsEntity::class);
        /** VariantsEntity $VariantsEntity */
        $VariantsEntity           = $this->entityFactory->get(VariantsEntity::class);
        /** NPCostDeliveryDataEntity $NPCostDeliveryDataEntity */
        $NPCostDeliveryDataEntity = $this->entityFactory->get(NPCostDeliveryDataEntity::class);
        /** NPCitiesEntity $NPCitiesEntity */
        $NPCitiesEntity           = $this->entityFactory->get(NPCitiesEntity::class);
        /** NPWarehousesEntity $NPWarehousesEntity */
        $NPWarehousesEntity       = $this->entityFactory->get(NPWarehousesEntity::class);

        $sendOrderData = new \stdClass();
        $sendOrderData->buyer_comment = (!empty($order->comment)) ? $order->comment : '';   //  комментарий пользователя
        $sendOrderData->manager_comment = (!empty($order->note)) ? $order->note : '' ;      //  комментарий админа

        //  получаем все статусы заказов и связанные с ними статусы заказов CRM
        $keycrm__send_status_when_update = $this->settings->get('keycrm__send_status_when_update');
        if ($keycrm__send_status_when_update == 1) {
            $OrderStatusesFind = $OrderStatusEntity->mappedBy('id')->cols(['id', 'idCRM', 'sendStatusCRM'])->find([]);
            if (!empty($OrderStatusesFind)  //  получили из БД данные статусов
                && !empty($order->status_id)  //  получили из БД данные статусов
                && !empty($OrderStatusesFind[$order->status_id]->idCRM) //  есть ассоциативный id из CRM для id статуса на сайте
                && ($OrderStatusesFind[$order->status_id]->idCRM != '') //  статус не пуст
            ) {
                //  собираем данные, обновляем статус заказа
                $sendOrderData->status_id = intval($OrderStatusesFind[$order->status_id]->idCRM);
            }
        }

        //  собираем товар
        $purchasesSends = $PurchasesEntity->find(['order_id' => $order->id]);
        $orderSend = $OrdersEntity->findOne(['id' => $order->id]);
        foreach ($purchasesSends as $key => $purchasesSend) {
            $productSend = $ProductsEntity->findOne(['id' => $purchasesSend->product_id]);
            if (!empty($productSend) && !empty($productSend->id)) {
                $variantSend = $VariantsEntity->findOne(['product_id' => $productSend->id]);
            } else {
                $variantSend = null;
            }
            $purchasesSend->product[$key] = $productSend;
            $purchasesSend->variant[$key] = $variantSend;
        }

        //  блок товаров
        $products = array();
        foreach ($purchasesSends as $key => $purchases) {
            if (empty($products[$key])) {
                $products[$key] = new \stdClass();
            }
            $products[$key]->sku = $purchases->sku;
            $products[$key]->id = intval($purchasesSend->product_id);
            $products[$key]->name = $purchases->product_name;
        }
        //  собираем данные, товары
        $sendOrderData->products = $products;

        //  get actual shipping info
        $getOrderShipping = $this->curlFunctions("order/" . $orderCrmID . "?include=shipping.lastHistory", 'GET');

        //  если есть уже в CRM признак доставки, тогда не передает данные на обновдение
        if (!empty($getOrderShipping)
            && !empty($getOrderShipping->shipping)
            && !empty($getOrderShipping->shipping->tracking_code)
        ) {
            $trackingCode = $getOrderShipping->shipping->tracking_code;
        }

        if (empty($trackingCode)) {

            //  блок получения CRM id способа доставки
            $shipping = new \stdClass();
            $deliveryMethod = $KeyCRMEntity->findOne(['value' => $orderSend->delivery_id, 'key' => 'deliveryCRM']);
            if (!empty($deliveryMethod)) {
                $delivery = $deliveryMethod;
                $shipping->delivery_service_id = $delivery->value2;
            }

            //NP
            $shippingNP = $NPCostDeliveryDataEntity->findOne(['order_id' => $orderSend->id]);

            if (!empty($shippingNP->warehouse_id)) {
                $NPciti = $NPCitiesEntity->findOne(['ref' => $shippingNP->city_id]);
                $NPadress = $NPWarehousesEntity->findOne(['ref' => $shippingNP->warehouse_id]);
            }

            if (!empty($shippingNP)) {
                $redelivery = $shippingNP->redelivery;

                if ($shippingNP->city_name) {
                    $shipping->shipping_address_city = $shippingNP->city_name;
                } else {
                    if (!empty($NPciti)) {
                        $shipping->shipping_address_city = $NPciti->name;
                    }
                }

                if ($shippingNP->area_name) {
                    $shipping->shipping_address_region = $shippingNP->area_name;
                }

                if ($shippingNP->street) {
                    $allAddress = $shippingNP->street . ' ' . $shippingNP->house . ' ' . $shippingNP->apartment;
                    $shipping->shipping_receive_point = $allAddress;
                } else {
                    if (!empty($NPadress)) {
                        $shipping->shipping_receive_point = $NPadress->name;
                    }
                }
            }
            //NP
            $shipping->shipping_address_country = 'Ukraine';
            $shipping->shipping_address_zip = ' ';
            if (empty($shipping->shipping_receive_point)) {
                if (class_exists(\Okay\Modules\OkayCMS\DeliveryFields\Helpers\DeliveryFieldsHelper::class)
                    && !empty($findModule = $this->entityFactory->get(ModulesEntity::class)->findOne(['vendor' => 'OkayCMS', 'module_name' => 'DeliveryFields']))
                    && ($findModule->enabled == 1)
                ) {
                    $select = $this->queryFactory->newSelect();
                    $select->cols([
                        DeliveryFieldsValuesEntity::getTableAlias() . '.field_id AS field_id',
                        DeliveryFieldsValuesEntity::getTableAlias() . '.order_id AS order_id',
                        DeliveryFieldsValuesEntity::getTableAlias() . '.value AS value',
                        DeliveryFieldsEntity::getTableAlias() . '.name AS name',
                        DeliveryFieldsEntity::getTableAlias() . '.position AS position',
                    ])
                        ->from(DeliveryFieldsEntity::getTable() . ' AS ' . DeliveryFieldsEntity::getTableAlias())
                        ->innerJoin(
                            Init::FIELD_DELIVERY_RELATION_TABLE . ' AS dfr',
                            DeliveryFieldsEntity::getTableAlias() . '.id = dfr.field_id AND dfr.delivery_id IN (?)',
                            [
                                (array)$orderSend->delivery_id,
                            ])
                        ->leftJoin(DeliveryFieldsValuesEntity::getTable() . ' AS ' . DeliveryFieldsValuesEntity::getTableAlias(),
                            DeliveryFieldsValuesEntity::getTableAlias() . '.field_id = dfr.field_id')
                        ->where(DeliveryFieldsValuesEntity::getTableAlias() . '.order_id = :order_id')->bindValue('order_id', (int)$orderSend->id)
                        ->orderBy(['position ASC']);

                    $this->db->query($select);
                    $fields = $this->db->results();

                    if (!empty($fields)) {
                        $address = [];
                        foreach ($fields as $field) {
                            if (!empty($field->name)) {
                                $address[] = $field->name . ": " . $field->value;
                            }
                        }
                    }
                }
                if (!empty($address)) {
                    if (is_array($address)) {
                        $address = (string)(implode('; ', $address));
                    }
                } else {
                    $address = '';
                }
                $shipping->shipping_receive_point = $address;
            }
        }

        //  собираем данные, способ доставки
        if (!empty($shipping) && !empty($shipping->delivery_service_id)) {
            $sendOrderData->shipping = $shipping;
        }

        //  отправляем запрос на обновление заказа
        $answerUpdateOrder = null;

        if (!empty($sendOrderData)) {
            $answerUpdateOrder = $this->curlFunctions(
                "order/" . $orderID,
                'PUT',
                $sendOrderData
            );

            /*  202	- Успішна відповідь від сервера.
              "id": 22,
              "parent_id": 1,               Ідентифікатор батьківського замовлення
              "source_uuid": "4815162342",
              "source_id": 11,              Ідентифікатор джерела
              "status_id": 1,               Ідентифікатор статусу замовлення
              "status_group_id": 1,
              "grand_total": 122.5,
              "promocode": "MERRYCHRISTMAS",
              "total_discount": 30.5,
              "expenses_sum": 20.5,
              "shipping_price": 2.5,
              "wrap_price": 3.5,
              "taxes": 2.5,
              "manager_comment": null,
              "buyer_comment": "Hello from buyer",
              "gift_message": "Happy Birthday Charlie",
              "is_gift": true,                          Позначено як подарунок
              "payment_status": "paid",                 Статус оплати  Enum: [ not_paid, part_paid, paid, overpaid ]
              "last_synced_at": "2020-05-16 17:00:07",  Дата останньої синхронізації із джерелом в UTC форматі
              "created_at": "2020-05-16 17:00:07",      Дата створення замовлення в UTC форматі
              "updated_at": "2020-05-16 17:00:07",      Дата останньої зміни замовлення в UTC форматі
              "closed_at": "2020-05-16 17:00:07"        Дата закриття замовлення в UTC форматі
            */
        }

        //  обновляем новые данные после обновлений в нашей БД
        if (!empty($answerUpdateOrder) && !empty($answerUpdateOrder->updated_at)) {
            $KeyCRMOrderEntity->update($KeyCRMOrder->id, [
                'source_id'        => $answerUpdateOrder->source_id,
                'status_on_source' => $answerUpdateOrder->status_id,
                'shipping_price'   => $answerUpdateOrder->shipping_price,
                'payment_status'   => $answerUpdateOrder->payment_status,
                'updated_at'       => $answerUpdateOrder->updated_at,
            ]);
        }

        return ExtenderFacade::execute(__METHOD__, $answerUpdatePayments, func_get_args());
    }

    /** метод производит запросы к API KeyCRM */
    public function curlFunctions($part, $method, $order = null)
    {
        try {

            $partFirst = $part;
            $orderFirst = null;

            if ($method == 'POST') {
                //$part = 'order';
                $request = $order;
                $orderFirst = $order;
                $request = json_encode($request, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }

            if ($method == 'PUT') {
                $request = $order;
                $orderFirst = $order;
                $request = json_encode($request, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }

            $KeyCRMOrderEntity = $this->entityFactory->get(KeyCRMOrderEntity::class);
            $KeyCRMEntity = $this->entityFactory->get(KeyCRMEntity::class);

            $apiKeyObj = $KeyCRMEntity->findOne(['key' => 'ApiKey']);
            if (empty($apiKeyObj) || empty($apiKeyObj->value)) {    //  без API ключа запросы не делаем
                return ExtenderFacade::execute(__METHOD__, null, func_get_args());
            }

            $apiKey = $apiKeyObj->value;

            $headers = [
                "Authorization: Bearer " . $apiKey,
                "accept: application/json",
                "Content-Type: application/json",
            ];
            $requestUrl = "https://openapi.keycrm.app/v1/$part";
            $curl = curl_init();
            if ($method == 'POST') {
                curl_setopt($curl, CURLOPT_POST, 1); //передача данных методом POST
                curl_setopt($curl, CURLOPT_POSTFIELDS, $request); //тут переменные которые будут переданы методом POST
            } else
                if ($method == 'PUT') {
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT'); //передача данных методом PUT
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $request); //тут переменные которые будут переданы методом POST
            }
            curl_setopt($curl, CURLOPT_URL, $requestUrl);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curl, CURLOPT_HEADER, 0);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($curl, CURLOPT_TIMEOUT, 15);

            $answer = null;

            $response = curl_exec($curl);
            $answer = (json_decode($response));
            $code = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            //$curl_getinfo = curl_getinfo($curl);
            //$curl_error = curl_error($curl);

            //  логирование
            $debudMode = $this->settings->get('keycrm__activate_debug');
            if (($debudMode == 1)                               //   включен в настройках отладочный режим
                && in_array($method, ['POST', 'PUT'])           //   срабатывать только когда методы записи данных, при чтении несрабатывать и разрешать чтение из API
                && !in_array($code, [200, 201, 202])            //   срабатывать только когда ошибки
            ) {
                file_put_contents(dirname(__DIR__)."/log/". pathinfo(__FILE__,  PATHINFO_BASENAME).'_'.__FUNCTION__.'.log',
                '<pre>'.var_export([
                    date('d.m.Y H:i:s') => $method,
                    'url' => $requestUrl,
                    '$request' => json_decode($request,1),
                    '$curl_getinfo http_code' => $code,
                    '$answer' => $answer,
                    //'$orderFirst' => @$orderFirst,
                    //'$curl_error' => @$curl_error,
                ],1).'</pre>', 8);
            }
            curl_close($curl);

            if (!empty($code) && !in_array($code, [200, 201, 202])
            ) {
                //  запишем в лог ошибку в товар
                if (!empty($orderFirst->source_uuid)
                    && !empty($answer->errors)
                ) {
                    $this->ordersEntity->update($orderFirst->source_uuid, ['error_crm' => '['. date('d-m-Y H:i:s') .'] '.print_r($answer->errors,1)]);
                }

                //  запишем в лог ошибку
                $this->logger->error('Error send ['.$code.'], answer cUrl: ' .  ((!empty($orderFirst) && !empty($orderFirst->source_uuid)) ? ('in order ' . $orderFirst->source_uuid) : '')) . ' ' .  print_r($answer,1);
            } else
            //  производим запись истории отправленных заказов только если это заказ и тип запроса POST
            if (!empty($answer)
                && ($method == 'POST') && ($partFirst == 'order')   //  если ответ от создания заказа
                && (empty($answer->errors))                         //  если ответ пришел без ошибки
            ) {
                if (!empty($orderFirst->source_uuid)) {
                    $this->ordersEntity->update($orderFirst->source_uuid, ['error_crm' => '']); //  очищаем ошибку
                }

                $CRTOrder = new \stdClass();

                $CRTOrder->idCRM = $answer->id;
                $CRTOrder->source_uuid          = $answer->source_uuid;
                $CRTOrder->global_source_uuid   = $answer->global_source_uuid;
                $CRTOrder->status_on_source     = $answer->status_on_source;
                $CRTOrder->source_id            = (int)$answer->source_id;
                $CRTOrder->client_id            = (int)$answer->client_id;
                $CRTOrder->grand_total          = strval($answer->grand_total);
                $CRTOrder->total_discount       = strval($answer->total_discount);
                $CRTOrder->expenses_sum         = strval($answer->expenses_sum);
                $CRTOrder->discount_amount      = strval($answer->discount_amount);
                $CRTOrder->discount_percent     = strval($answer->discount_percent);
                $CRTOrder->shipping_price       = $answer->shipping_price;
                $CRTOrder->payment_status       = $answer->payment_status;
                $CRTOrder->created_at = $answer->created_at;
                $CRTOrder->updated_at = $answer->updated_at;
                $CRTOrder->ordered_at = $answer->ordered_at;

                $KeyCRMOrderEntity->add($CRTOrder);
            } elseif (!empty($answer->errors))
            {   //  запишем в лог ошибку
                $this->logger->error('Error answer: ' . print_r($answer->errors,1));
            }

        } catch (Exception $e) {
            $this->logger->error('An error occurred while sending the request: ' . $e->getMessage());
        }

        return ExtenderFacade::execute(__METHOD__, $answer, func_get_args());
    }

    public function addRecordToKeyCRMOrder($answer) {
        $lastId = null;

        $CRTOrder = new \stdClass();

        $CRTOrder->idCRM                = $answer->id;
        $CRTOrder->source_uuid          = $answer->source_uuid;
        $CRTOrder->global_source_uuid   = $answer->global_source_uuid;
        $CRTOrder->status_on_source     = $answer->status_on_source;
        $CRTOrder->source_id            = strval($answer->source_id);
        $CRTOrder->client_id            = strval($answer->client_id);
        $CRTOrder->grand_total          = strval($answer->grand_total);
        $CRTOrder->total_discount       = strval($answer->total_discount);
        $CRTOrder->expenses_sum         = strval($answer->expenses_sum);
        $CRTOrder->discount_amount      = strval($answer->discount_amount);
        $CRTOrder->discount_percent     = strval($answer->discount_percent);
        $CRTOrder->shipping_price       = $answer->shipping_price;
        $CRTOrder->payment_status       = $answer->payment_status;
        $CRTOrder->created_at           = $answer->created_at;
        $CRTOrder->updated_at           = $answer->updated_at;
        $CRTOrder->ordered_at           = $answer->ordered_at;

        if (!empty($CRTOrder)) {
            $KeyCRMOrderEntity = $this->entityFactory->get(KeyCRMOrderEntity::class);
            $lastId = $KeyCRMOrderEntity->add($CRTOrder);
        }

        return ExtenderFacade::execute(__METHOD__, $lastId, func_get_args());
    }

    //  Узнаем кол-во не отправленных заказов
    public function synchronizeCountOrders()
    {
        $allOrdersIds = [];
        $diffOrders = [];
        $count = 0;
        $accessOrdersStatusIds = [];

        $KeyCRMEntity = $this->entityFactory->get(KeyCRMEntity::class);

        //  ищем те статусы, заказы в которых наобходимо обрабатывать
        $accessOrders = $this->OrderStatusEntity->cols(['id','sendStatusCRM'])->find([ 'sendStatusCRM' => 1]);
        if (!empty($accessOrders)) {
            foreach ($accessOrders as $status) {
                if (!empty($status->sendStatusCRM)) {
                    $accessOrdersStatusIds[] = $status->id;
                }
            }
        }

        //  узнаем кол-во заказов которые сейчас есть в заказах и которые уже отправлены
        $select = $this->queryFactory->newSelect();
        $select->cols(['DISTINCT o.id AS id', 'koe.source_uuid AS source_uuid'])->from(KeyCRMOrderEntity::getTable() . ' AS koe')
            ->leftJoin(OrdersEntity::getTable().' AS  o', 'koe.source_uuid = o.id')
        ->where('o.id != ""');

        $this->db->query($select);
        $sendOrders = $this->db->results('id');

        $allKeyCRMOrderEntityIds = (!empty($sendOrders)) ? $sendOrders : [];

        if (!empty($allKeyCRMOrderEntityIds)) {
            //  получаем заказы из Okay и только по тем статусам которые необходимо обрабатывать и если активировано то и старше указанной даты
            $selectCountOrders = $this->queryFactory->newSelect();
            $selectCountOrders->cols(['DISTINCT o.id AS id'])->from(OrdersEntity::getTable() . ' AS ' . OrdersEntity::getTableAlias())
                ->where('status_id IN (:status_id)')->bindValue('status_id', $accessOrdersStatusIds)
                ->orderBy(['id DESC']);

            //  если активирована выборка заказов только старше определенной даты
            $used_add_timer_block__date_before = $this->settings->get('used_add_timer_block__date_before');     //  галочка активации ограничения выбора заказов по дате
            if ($used_add_timer_block__date_before == 1) {
                //  тогда получаем выбранную админом дату
                $add_timer_block__date_before = $this->settings->get('add_timer_block__date_before');               //  дата ограничения выборки заказов на отправку
                list($day, $month, $year) = explode('.', !empty($add_timer_block__date_before) ? $add_timer_block__date_before : date('d.m.Y'));
                if (!empty($day) && !empty($month) && !empty($year)) {
                    $setUnixTime = strtotime($year . '-' . $month . '-' . $day);
                    //  если выбранная дата
                    if ($setUnixTime <= time()) {
                        $selectCountOrders->where('date > :date')->bindValue('date', date('Y-m-d', $setUnixTime) . ' 00:00:00');
                    }
                }
            }

            $this->db->query($selectCountOrders);
            $allOrdersIds = $this->db->results('id');
        }

        //  находим расхождения и перебираем только те заказы, которые есть в заказах и нет в отправленных
        $diffOrders = array_diff($allOrdersIds, $allKeyCRMOrderEntityIds);

        if (!empty($diffOrders) && is_array($diffOrders)) {
            $count = count($diffOrders);
        }

        return $count;
    }

        //  массовая отправка не отправленных заказов
    public function synchronizeOrders()
    {
        set_time_limit(5 * 60); //  5 мин
        $keycrm__amount_paket_orders = $this->settings->get('keycrm__amount_paket_orders'); //  кол-во в одном пакете
        $allOrdersIds = [];
        $count = $countWork = 0;
        $KeyCRMEntity       = $this->entityFactory->get(KeyCRMEntity::class);
        $KeyCRMOrderEntity  = $this->entityFactory->get(KeyCRMOrderEntity::class);
        $OrdersEntity       = $this->entityFactory->get(OrdersEntity::class);

        //  ищем те статусы, заказы в которых наобходимо обрабатывать
        $accessOrders = $this->OrderStatusEntity->cols(['id','sendStatusCRM' ])->find([ 'sendStatusCRM' => 1]);
        if (!empty($accessOrders)) {
            foreach ($accessOrders as $status) {
                if (!empty($status->sendStatusCRM)) {
                    $accessOrdersStatusIds[] = $status->id;
                }
            }
        }

        //  получаем отправленные
        $allKeyCRMOrderEntityIds = [];
        $allKeyCRMOrderEntityData = $KeyCRMOrderEntity->cols(['id','source_uuid'])->find([]);
        if (!empty($allKeyCRMOrderEntityData)){
            foreach ($allKeyCRMOrderEntityData as $elem) {
                if (!empty($elem->source_uuid)) {
                    $allKeyCRMOrderEntityIds[] = $elem->source_uuid;
                }
            }
        }

        //  получаем заказы из Okay и только по тем статусам которые необходимо обрабатывать
        $selectCountOrders = $this->queryFactory->newSelect();
        $selectCountOrders->cols(['*'])->from(OrdersEntity::getTable() . ' AS ' . OrdersEntity::getTableAlias())
            ->where('status_id IN (:status_id)')->bindValue('status_id', $accessOrdersStatusIds)
            ->orderBy(['id DESC']);

        $used_add_timer_block__date_before = $this->settings->get('used_add_timer_block__date_before');     //  галочка активации ограничения выбора заказов по дате
        if ($used_add_timer_block__date_before == 1) {
            $add_timer_block__date_before = $this->settings->get('add_timer_block__date_before');               //  дата ограничения выборки заказов на отправку
            list($day, $month, $year) = explode('.', !empty($add_timer_block__date_before) ? $add_timer_block__date_before : date('d.m.Y'));
            if (!empty($day) && !empty($month) && !empty($year)) {
                $setUnixTime = strtotime($year . '-' . $month . '-' . $day);
                if ($setUnixTime <= time()) {
                    $selectCountOrders->where('date > :date')->bindValue('date', date('Y-m-d', $setUnixTime) . ' 00:00:00');
                }
            }
        }

        $this->db->query($selectCountOrders);
        $allOrdersData = $this->db->results();
        $allOrdersIds = [];
        if (!empty($allOrdersData)) {
            foreach ($allOrdersData as $allOrderElem) {
                if (!empty($allOrderElem->id)) {
                    $allOrders[$allOrderElem->id] = $allOrderElem;
                    $allOrdersIds[$allOrderElem->id] = $allOrderElem->id;
                }
            }
        }

        //  находим расхождения и перебираем только те заказы, которые есть в заказах и нет в отправленных
        $diffOrders = array_diff($allOrdersIds, $allKeyCRMOrderEntityIds);

        if (!empty($diffOrders) && !empty($accessOrders)) {
            foreach ($accessOrders as $status) {
                //  на каждый статус запрос всех заказов с таким статусом
                if (!empty($status->value)) {

                    $orders = $OrdersEntity->mappedBy('id')->cols(['id', 'status_id'])->find(['id' => $diffOrders, 'status_id' => $status->id]);

                    if (!empty($orders) && !empty($allOrders)) {
                        //  перебираем заказы и делаем индивидуальную отправку каждого
                        foreach ($orders as $orderId => $order) {
                            $count++;
                            if (!empty($allOrders[$orderId]) //  есть такой заказ который передается в метод
                            ) {
                                $result = $this->OrderRequest($allOrders[$orderId], 1);

                                if (!empty($result)) {
                                    if (!empty($result->message) && ($result->message == 'Too Many Attempts')) {

                                        return ExtenderFacade::execute(__METHOD__, ['count' => $countWork, 'error' => 'Too Many Attempts'], func_get_args());

                                    } elseif (empty($allOrders[$orderId]->error_crm)) {
                                        $countWork++;
                                    }
                                }
                            }

                            //  ограничитель выполнения
                            if (!empty($keycrm__amount_paket_orders)
                                && (($countWork >= $keycrm__amount_paket_orders) || ($count >= 2000))
                            ) { //  если кол-во в одном пакете установлено тогда прерываем цикл
                                break 2;
                            }
                        }
                    }
                }
            }
        }

        return ExtenderFacade::execute(__METHOD__, ['count' => $countWork, 'error' => null], func_get_args());
    }

}