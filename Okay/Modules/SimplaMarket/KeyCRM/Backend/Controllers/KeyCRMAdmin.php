<?php

namespace Okay\Modules\SimplaMarket\KeyCRM\Backend\Controllers;

use Okay\Admin\Controllers\IndexAdmin;
use Okay\Admin\Helpers\BackendDeliveriesHelper;
use Okay\Admin\Helpers\BackendOrdersHelper;
use Okay\Core\Database;
use Okay\Core\EntityFactory;
use Okay\Core\QueryFactory;
use Okay\Entities\OrdersEntity;
use Okay\Entities\OrderStatusEntity;
use Okay\Entities\PaymentsEntity;
use Okay\Modules\SimplaMarket\KeyCRM\Entities\KeyCRMEntity;
use Okay\Modules\SimplaMarket\KeyCRM\Entities\KeyCRMDeliveryServicesEntity;
use Okay\Modules\SimplaMarket\KeyCRM\Entities\KeyCRMStatusesEntity;
use Okay\Modules\SimplaMarket\KeyCRM\Extenders\BackendExtender;
use Okay\Modules\SimplaMarket\KeyCRM\Helpers\KeyCRMHelper;

class KeyCRMAdmin extends IndexAdmin
{
    public function fetch(
        EntityFactory           $entityFactory,
        BackendExtender         $backendExtender,
        BackendOrdersHelper     $backendOrdersHelper,
        BackendDeliveriesHelper $backendDeliveriesHelper,
        KeyCRMHelper            $keyCRMHelper,
        QueryFactory            $queryFactory,
        Database                $db
    )
    {
        $start = microtime(true);
        /** @var KeyCRMEntity $KeyCRMEntity */
        $KeyCRMEntity                 = $entityFactory->get(KeyCRMEntity::class);
        $KeyCRMStatusesEntity         = $entityFactory->get(KeyCRMStatusesEntity::class);
        $KeyCRMDeliveryServicesEntity = $entityFactory->get(KeyCRMDeliveryServicesEntity::class);
        $paymentsEntity               = $entityFactory->get(PaymentsEntity::class);
        $OrdersEntity                 = $entityFactory->get(OrdersEntity::class);
        $OrderStatusEntity            = $entityFactory->get(OrderStatusEntity::class);

        $ApiKeyEntity    =  $KeyCRMEntity->findOne(['key'=>'ApiKey']);
        $sourceCRMEntity =  $KeyCRMEntity->findOne(['key'=>'sourseCRM']);
        $statusCRMEntity =  $KeyCRMEntity->findOne(['key'=>'statusCRM']);

        if ($this->request->method('post')) {
            if(!empty($this->request->post('sendAllOrder') == 1)) {
                $keycrm__amount_paket_orders = $this->request->post('keycrm__amount_paket_orders', 'integer');
                $this->settings->set('keycrm__amount_paket_orders', (isset($keycrm__amount_paket_orders) ? $keycrm__amount_paket_orders : 20));

                //  происходит запуск того же метода в контроллере, который запускае и Cron
                $synchronizeOrders = $keyCRMHelper->synchronizeOrders();
                $countWorkOrders = $synchronizeOrders['count'];
                $error = $synchronizeOrders['error'];

                if (!empty($error)) {
                    $this->design->assign('message_error', $error);
                } else {
                    $this->design->assign('message_success', 1);
                }
                $this->design->assign('count_work_orders', $countWorkOrders);
                $this->design->assign('execution_time', number_format(microtime(true) - $start, 2));

                $this->postRedirectGet->storeMessageSuccess('import');
            } else {
                $this->postRedirectGet->storeMessageSuccess('updated');

                $keycrm__amount_paket_orders = $this->request->post('keycrm__amount_paket_orders', 'integer');
                $this->settings->set('keycrm__amount_paket_orders', (isset($keycrm__amount_paket_orders) ? $keycrm__amount_paket_orders : 20));
                //$this->settings->set('keycrm_key_send_all_order_through_cron', $this->request->post('keycrm_key_send_all_order_through_cron', 'integer', 0));
                $this->settings->set('keycrm__activate_debug', $this->request->post('keycrm__activate_debug', 'integer', 0));
                $this->settings->set('keycrm__send_status_when_update', $this->request->post('keycrm__send_status_when_update', 'integer', 0));

                $this->settings->set('keycrm__checkbox_disable_send_delivery_price', $this->request->post('keycrm__checkbox_disable_send_delivery_price', 'integer', 0));
                $this->settings->set('keycrm__checkbox_disable_send_separate_delivery_price', $this->request->post('keycrm__checkbox_disable_send_separate_delivery_price', 'integer', 0));

                //  галочка активации ограничения выбора заказов по дате
                $this->settings->set('used_add_timer_block__date_before', $this->request->post('used_add_timer_block__date_before', 'integer', 0));
                //  дата ограничения выборки заказов на отправку
                $this->settings->set('add_timer_block__date_before', $this->request->post('add_timer_block__date_before', 'string', ''));

                //add api key in admin panel
                $apiKey = new \stdClass();
                $apiKey->key = 'ApiKey';
                $apiKey->value = $this->request->post('api_key_crm');
                if (empty($apiKey->value)) {
                    $apiKey->value = '';
                }

                if (empty($ApiKeyEntity)) {
                    $KeyCRMEntity->add($apiKey);
                } else {
                    $KeyCRMEntity->update($ApiKeyEntity->id, $apiKey);
                }
                //end part code add api key

                $checkApi = $backendExtender->testKeyAPI($apiKey);
                if (!empty($checkApi)) {
                    $this->design->assign('error_api', $checkApi);
                    $this->design->assign('message_error', 'error_api');
                }

                // source choise select
                if (!empty($this->request->post('source_crm'))) {

                    $sourceCRM = new \stdClass();
                    $sourceCRM->key = 'sourseCRM';
                    $sourceCRM->value = $this->request->post('source_crm');
                    if (empty($sourceCRMEntity)) {
                        $KeyCRMEntity->add($sourceCRM);
                    } else {
                        $KeyCRMEntity->update($sourceCRMEntity->id, $sourceCRM);
                    }
                }

                //status choise select
                /*if (!empty($this->request->post('statuses'))) {

                    $statusCRM = new \stdClass();
                    $sendStatusPost = new \stdClass();
                    $statusList = $this->request->post('statuses');
                    //  is_close id crm in table crm, id it is id status in site
                    foreach ($statusList['id'] as $key => $statusItem) {
                        $statusCRM->key = 'statusCRM';
                        $statusCRM->value = $statusItem;
                        $statusCRM->value2 = !empty($statusList['is_close'][$key]) ? $statusList['is_close'][$key] : '';
                        if (empty($KeyCRMEntity->findOne(['value' => $statusItem, 'key' => 'statusCRM'])->id)) {
                            $KeyCRMEntity->add($statusCRM);
                        } else {
                            $idStatus = intval($KeyCRMEntity->findOne(['value' => $statusItem, 'key' => 'statusCRM'])->id);
                            $KeyCRMEntity->update($idStatus, $statusCRM);
                        }
                        //  send status
                        $sendStatusPost->key = 'sendStatus';
                        $sendStatusPost->value = $statusItem;
                        $sendStatusPost->value2 = $statusList['is_send'][$key];
                        if (empty($KeyCRMEntity->findOne(['value' => $statusItem, 'key' => 'sendStatus'])->id)) {
                            $KeyCRMEntity->add($sendStatusPost);
                        } else {
                            $idStatus = intval($KeyCRMEntity->findOne(['value' => $statusItem, 'key' => 'sendStatus'])->id);
                            $KeyCRMEntity->update($idStatus, $sendStatusPost);
                        }
                        // end send status
                    }
                }*/
                //  end part status

                //  start part crm statuses
                //  получаем связанные статусы и при наличии нужных данных сохраняем у статуса закааза связанный id
                if (!empty($crmStatuses = $this->request->post('statuses'))) {

                    foreach ($crmStatuses as $orderStatusId => $crmStatus) {
                        if (!empty($orderStatusId)) {
                            $OrderStatusEntity->update($orderStatusId, [
                                'idCRM' => $crmStatus['crm_statuses'],
                                'sendStatusCRM' => !empty($crmStatus['is_send']) ? 1 : 0,
                            ]);
                        }
                    }
                }
                //  end part crm statuses

                //  payment choise select
                if (!empty($this->request->post('payment'))) {

                    $paymentCRM = new \stdClass();
                    $paymentList = $this->request->post('payment');
                    // is_close id crm in table crm, id it is id status in site
                    foreach ($paymentList['id'] as $key => $paymentItem) {

                        $paymentCRM->key = 'paymentCRM';
                        $paymentCRM->value = $paymentItem;
                        $paymentCRM->value2 = $paymentList['is_close'][$key];

                        if (empty($KeyCRMEntity->findOne(['value' => $paymentItem, 'key' => 'paymentCRM'])->id)) {
                            $KeyCRMEntity->add($paymentCRM);
                        } else {
                            $idStatus = intval($KeyCRMEntity->findOne(['value' => $paymentItem, 'key' => 'paymentCRM'])->id);
                            $KeyCRMEntity->update($idStatus, $paymentCRM);
                        }
                    }
                }

                //  delivery choise select
                if (!empty($this->request->post('deliverie'))) {

                    $deliveryCRM = new \stdClass();
                    $deliveryList = $this->request->post('deliverie');

                    //  is_close id crm in table crm, id it is id status in site
                    foreach ($deliveryList['id'] as $key => $deliveryItem) {
                        $deliveryCRM->key = 'deliveryCRM';
                        $deliveryCRM->value = $deliveryItem;
                        $deliveryCRM->value2 = $deliveryList['is_close'][$key];
                        if (empty($KeyCRMEntity->findOne(['value' => $deliveryItem, 'key' => 'deliveryCRM'])->id)) {
                            $KeyCRMEntity->add($deliveryCRM);
                        } else {
                            $idStatus = intval($KeyCRMEntity->findOne(['value' => $deliveryItem, 'key' => 'deliveryCRM'])->id);
                            $KeyCRMEntity->update($idStatus, $deliveryCRM);
                        }
                    }
                }
                //  end part delivery

                if (empty($this->design->getVar('message_error'))) {
                    $this->postRedirectGet->redirect();
                }
            }
        }

        $ordersStatusesKeyCRM = [];

        // source
        if (!empty($ApiKeyEntity) && !empty($ApiKeyEntity->value)){
            $sourceCRM   = $backendExtender->sourseList($ApiKeyEntity->value);
            $statusCRM   = $backendExtender->statusList($ApiKeyEntity->value);
            $deliveryCRM = $backendExtender->deliveryList($ApiKeyEntity->value);
            $paymentCRM  = $backendExtender->paymentList($ApiKeyEntity->value);

            /*file_put_contents(__DIR__."/". pathinfo(__FILE__,  PATHINFO_BASENAME).'_'.__FUNCTION__.'.txt',
            '<pre>'.print_r([
                '$statusCRM' => $statusCRM
                ],1).'</pre>', 8);*/

            $paymentMethods = $paymentsEntity->find();
            //  получаем способы доставки
            $filter         = $backendDeliveriesHelper->buildDeliveriesFilter();
            $deliveries     = $backendDeliveriesHelper->findDeliveries($filter);

            $statusCRMChoice   = $KeyCRMEntity->mappedBy('value')->find(['key'=>'statusCRM']);
            $paymentCRMChoice  = $KeyCRMEntity->mappedBy('value')->find(['key'=>'paymentCRM']);
            $deliveriCRMChoice = $KeyCRMEntity->mappedBy('value')->find(['key'=>'deliveryCRM']);
            $sendStatus        = $KeyCRMEntity->mappedBy('value')->find(['key'=>'sendStatus']);

            $this->design->assign('sourceCRM',  (!empty($sourceCRM->data) ? $sourceCRM->data : null));
            $this->design->assign('statusCRM',  (!empty($statusCRM->data) ? $statusCRM->data : null));
            $this->design->assign('deliveryCRM',(!empty($deliveryCRM->data) ?  $deliveryCRM->data : null));
            $this->design->assign('paymentCRM', (!empty($paymentCRM->data) ? $paymentCRM->data : null));
            $this->design->assign('statusCRMChoice',    $statusCRMChoice);
            $this->design->assign('paymentCRMChoice',   $paymentCRMChoice);
            $this->design->assign('deliveriCRMChoice',  $deliveriCRMChoice);
            $this->design->assign('sendStatus',         $sendStatus);
            $this->design->assign('payment_methods',    $paymentMethods);
            $this->design->assign('deliveries',         $deliveries);

            // все статусы заказов Okay
            $ordersStatuses = $backendOrdersHelper->findStatuses();
            $this->design->assign('orders_statuses', $ordersStatuses);

            // все статусы заказов KeyCRM
            $ApiKeyEntity =  $KeyCRMEntity->findOne(['key'=>'ApiKey']); //  получили ApiKey
            if (!empty($ApiKeyEntity) && !empty($ApiKeyEntity->value)) {
                if (!empty($statusCRM)&& !empty($statusCRM->data)) {
                    $ordersStatusesKeyCRM = $statusCRM->data;
                } else {
                    $ordersStatusesKeyCRM = [];
                }
            }
        }

        // количество заказов без признака отправки в CRM
        $countSendOrders = $keyCRMHelper->synchronizeCountOrders();
        $this->design->assign('count_send_orders', $countSendOrders);
        $this->design->assign('count_all_orders', $OrdersEntity->count());

        //  количество заказов с ошибками
        $select = $queryFactory->newSelect();
        $select->cols(['count(id) AS count'])->from(OrdersEntity::getTable())
            ->where('error_crm IS NOT NULL')
            ->where('error_crm != ""');
        $this->db->query($select);
        $countErrorOrders = $db->result('count');
        $this->design->assign('count_error_orders', (!empty($countErrorOrders)) ? $countErrorOrders : 0 );



        $this->design->assign('sourceCRMID', $sourceCRMEntity);

        $this->design->assign('crm_orders_statuses', $ordersStatusesKeyCRM);

        $this->design->assign('api_key', (!empty($ApiKeyEntity) && !empty($ApiKeyEntity->value)) ? $ApiKeyEntity->value : '');

        $this->response->setContent($this->design->fetch('kyecrmadmin.tpl'));
    }
}