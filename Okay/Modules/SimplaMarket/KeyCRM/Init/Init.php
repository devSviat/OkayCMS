<?php


namespace Okay\Modules\SimplaMarket\KeyCRM\Init;

use Okay\Admin\Helpers\BackendOrdersHelper;
use Okay\Core\Modules\AbstractInit;
use Okay\Core\Modules\EntityField;
use Okay\Entities\OrdersEntity;
use Okay\Entities\OrderStatusEntity;
use Okay\Helpers\MainHelper;
use Okay\Helpers\OrdersHelper;
use Okay\Modules\SimplaMarket\KeyCRM\Entities\KeyCRMOrderEntity;
use Okay\Modules\SimplaMarket\KeyCRM\Entities\KeyCRMPaymentMethodsEntity;
use Okay\Modules\SimplaMarket\KeyCRM\Entities\KeyCRMSourceEntity;
use Okay\Modules\SimplaMarket\KeyCRM\Entities\KeyCRMEntity;
use Okay\Modules\SimplaMarket\KeyCRM\Entities\KeyCRMDeliveryServicesEntity;
use Okay\Modules\SimplaMarket\KeyCRM\Entities\KeyCRMStatusesEntity;
use Okay\Modules\SimplaMarket\KeyCRM\Extenders\BackendExtender;
use Okay\Modules\SimplaMarket\KeyCRM\Extenders\FrontendExtender;
use Okay\Modules\SimplaMarket\KeyCRM\Helpers\RefererCRMHelper;


class Init extends AbstractInit
{
    const COLUMN_ID_CRM = 'idCRM';
    const COLUMN_SEND_CRM = 'sendStatusCRM';

    public function install()
    {
        $this->setBackendMainController('KeyCRMAdmin');

        $this->migrateEntityTable(KeyCRMEntity::class, [
            (new EntityField('id'))->setIndexPrimaryKey()->setTypeInt(11, false)->setAutoIncrement(),
            (new EntityField('key'))->setTypeVarchar(255, true),
            (new EntityField('value'))->setTypeText()->setNullable(),
            (new EntityField('value2'))->setTypeText()->setNullable(),
        ]);

        $this->migrateEntityTable(KeyCRMSourceEntity::class, [
            (new EntityField('id'))->setIndexPrimaryKey()->setTypeInt(11, false)->setAutoIncrement(),
            (new EntityField('idCRM'))->setTypeInt(11, false),
            (new EntityField('name'))->setTypeVarchar(100, true)->setNullable(),
            (new EntityField('alias'))->setTypeVarchar(255, true)->setNullable(),
            (new EntityField('driver'))->setTypeVarchar(255, true)->setNullable(),
            (new EntityField('source_name'))->setTypeVarchar(255, true)->setNullable(),
            (new EntityField('source_uuid'))->setTypeInt(11, false)->setNullable(),
            (new EntityField('currency_code'))->setTypeVarchar(255, true)->setNullable(),
            (new EntityField('source'))->setTypeVarchar(255, true)->setNullable(),
            (new EntityField('expense_type_id'))->setTypeInt(11, false)->setNullable(),
            (new EntityField('with_expenses'))->setTypeInt(11, false)->setNullable(),
            (new EntityField('created_at'))->setTypeVarchar(35, true)->setNullable(),
            (new EntityField('updated_at'))->setTypeVarchar(35, true)->setNullable(),
            (new EntityField('deleted_at'))->setTypeVarchar(35, true)->setNullable(),
        ]);

        $this->migrateEntityTable(KeyCRMStatusesEntity::class, [
            (new EntityField('id'))->setIndexPrimaryKey()->setTypeInt(11, false)->setAutoIncrement(),
            (new EntityField('idCRM'))->setTypeInt(11, false),
            (new EntityField('name'))->setTypeVarchar(255, true)->setNullable(),
            (new EntityField('alias'))->setTypeVarchar(255, true)->setNullable(),
            (new EntityField('is_active'))->setTypeTinyInt(1, false)->setNullable(),
            (new EntityField('group_id'))->setTypeInt(11, false)->setNullable(),
            (new EntityField('is_closing_order'))->setTypeTinyInt(1, false)->setNullable(),
            (new EntityField('is_reserved'))->setTypeTinyInt(1, false)->setNullable(),
            (new EntityField('expiration_period'))->setTypeText()->setNullable(),
            (new EntityField('created_at'))->setTypeVarchar(35, true)->setNullable(),
            (new EntityField('updated_at'))->setTypeVarchar(35, true)->setNullable(),
        ]);

        $this->migrateEntityTable(KeyCRMPaymentMethodsEntity::class, [
            (new EntityField('id'))->setIndexPrimaryKey()->setTypeInt(11, false)->setAutoIncrement(),
            (new EntityField('idCRM'))->setTypeInt(11, false),
            (new EntityField('name'))->setTypeVarchar(100, true)->setNullable(),
            (new EntityField('alias'))->setTypeVarchar(255, true)->setNullable(),
            (new EntityField('is_active'))->setTypeTinyInt(1, false)->setNullable(),
        ]);

        $this->migrateEntityTable(KeyCRMDeliveryServicesEntity::class, [
            (new EntityField('id'))->setIndexPrimaryKey()->setTypeInt(11, false)->setAutoIncrement(),
            (new EntityField('idCRM'))->setTypeInt(11, false),
            (new EntityField('name'))->setTypeVarchar(255, true)->setNullable(),
            (new EntityField('source_name'))->setTypeVarchar(255, true)->setNullable(),
            (new EntityField('alias'))->setTypeVarchar(255, true)->setNullable(),
        ]);

        $this->migrateEntityTable(KeyCRMOrderEntity::class, [
            (new EntityField('id'))->setIndexPrimaryKey()->setTypeInt(11, false)->setAutoIncrement(),
            (new EntityField('idCRM'))->setTypeInt(11, false),
            (new EntityField('source_uuid'))->setTypeInt(11, false)->setNullable()->setIndexUnique(),
            (new EntityField('global_source_uuid'))->setTypeInt(11, false)->setNullable(),
            (new EntityField('status_on_source'))->setTypeText()->setNullable(),
            (new EntityField('source_id'))->setTypeInt(11, false)->setNullable(),
            (new EntityField('client_id'))->setTypeInt(11, false)->setNullable(),
            (new EntityField('grand_total'))->setTypeText()->setNullable(),
            (new EntityField('total_discount'))->setTypeText()->setNullable(),
            (new EntityField('margin_sum'))->setTypeText()->setNullable(),
            (new EntityField('expenses_sum'))->setTypeText()->setNullable(),
            (new EntityField('discount_amount'))->setTypeText()->setNullable(),
            (new EntityField('discount_percent'))->setTypeText()->setNullable(),
            (new EntityField('shipping_price'))->setTypeText()->setNullable(),
            (new EntityField('payment_status'))->setTypeVarchar(35, true)->setNullable(),
            (new EntityField('created_at'))->setTypeVarchar(35, true)->setNullable(),
            (new EntityField('updated_at'))->setTypeVarchar(35, true)->setNullable(),
            (new EntityField('ordered_at'))->setTypeVarchar(35, true)->setNullable(),
        ]);

        //  добавляем в статус заказа столбец соответствию статуса в CRM
        $this->migrateEntityField(OrderStatusEntity::class, (new EntityField(self::COLUMN_ID_CRM))->setTypeInt(1, true)->setDefault('0'));
        $this->migrateEntityField(OrderStatusEntity::class, (new EntityField(self::COLUMN_SEND_CRM))->setTypeTinyInt(1, true)->setDefault('0'));

        $this->migrateEntityField(OrdersEntity::class, (new EntityField('referer_term'))->setTypeVarchar(55)->setNullable());
        $this->migrateEntityField(OrdersEntity::class, (new EntityField('utm_medium'))->setTypeVarchar(55)->setNullable());
        $this->migrateEntityField(OrdersEntity::class, (new EntityField('utm_source'))->setTypeVarchar(55)->setNullable());
        $this->migrateEntityField(OrdersEntity::class, (new EntityField('utm_campaign'))->setTypeVarchar(55)->setNullable());
        $this->migrateEntityField(OrdersEntity::class, (new EntityField('utm_term'))->setTypeVarchar(55)->setNullable());
        $this->migrateEntityField(OrdersEntity::class, (new EntityField('utm_content'))->setTypeVarchar(55)->setNullable());
        $this->migrateEntityField(OrdersEntity::class, (new EntityField('error_crm'))->setTypeVarchar(500)->setNullable());
    }

    public function init()
    {
        $this->registerBackendController('KeyCRMAdmin');
        $this->addBackendControllerPermission('KeyCRMAdmin', 'okaycms__keycrm__keycrm');

        //  регистрируем добавленные в БД столбцы
        $this->registerEntityField(OrderStatusEntity::class, self::COLUMN_ID_CRM);
        $this->registerEntityField(OrderStatusEntity::class, self::COLUMN_SEND_CRM);

        $this->registerEntityField(OrdersEntity::class, 'referer_term');
        $this->registerEntityField(OrdersEntity::class, 'utm_medium');
        $this->registerEntityField(OrdersEntity::class, 'utm_source');
        $this->registerEntityField(OrdersEntity::class, 'utm_campaign');
        $this->registerEntityField(OrdersEntity::class, 'utm_term');
        $this->registerEntityField(OrdersEntity::class, 'utm_content');
        $this->registerEntityField(OrdersEntity::class, 'error_crm');

        //  Backend
        $this->addBackendBlock('order_contact', 'referer_block_in_order.tpl');
        $this->addBackendBlock('order_contact', 'order_error.tpl');         //  текст ошибки
        $this->addBackendBlock('orders_list_name', 'orders_error.tpl');     //  инфо о ошибке в списке заказов
        $this->addBackendBlock('order_custom_block', 'checkbox_order.tpl'); //  переключатель отправки в заказ

        $this->registerQueueExtension(
            [BackendOrdersHelper::class, 'findOrder'],
            [BackendExtender::class,     'sendPayedOrder']
        );
        $this->registerQueueExtension(
            [BackendOrdersHelper::class, 'findOrder'],
            [BackendExtender::class,     'tplOrderAddInfo']
        );

        //  Frontend
        //  Метод запускается в OrderController, при открытии заказа
        $this->registerQueueExtension(
            [OrdersHelper::class,    'getOrderPaymentMethodsList'],
            [FrontendExtender::class, 'sendCartOrder']
        );

        //  отслеживаем на каждой странице переход из внешнего ресурса для отлова UTM меток
        $this->registerQueueExtension(
            [MainHelper::class,       'commonAfterControllerProcedure'],
            [RefererCRMHelper::class, 'commonAfterControllerProcedure']
        );

        $this->registerChainExtension(
            [OrdersHelper::class,     'prepareAdd'],
            [FrontendExtender::class, 'prepareAdd']
        );

        //  необходимо еще обрабатывать изменение/сохранение заказов чтобы перезать состояние оплаты в CRM
        $this->registerQueueExtension(
            [OrdersEntity::class,     'markedPaid'],
            [FrontendExtender::class, 'markedPaid']
        );

        //  В меню заказы добавляем меню для большего удобства
        $this->extendBackendMenu('left_orders', [
            'left_keycrm_menu' => ['KeyCRMAdmin'],
        ], '<svg width="22px" height="22px" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6"><g id="shopping-cart"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"></path></g></svg>');
    }

    public function update_1_1_1()
    {
        $this->migrateEntityField(OrdersEntity::class, (new EntityField('referer_term'))->setTypeVarchar(55)->setNullable());
        $this->migrateEntityField(OrdersEntity::class, (new EntityField('utm_medium'))->setTypeVarchar(55)->setNullable());
        $this->migrateEntityField(OrdersEntity::class, (new EntityField('utm_source'))->setTypeVarchar(55)->setNullable());
        $this->migrateEntityField(OrdersEntity::class, (new EntityField('utm_campaign'))->setTypeVarchar(55)->setNullable());
        $this->migrateEntityField(OrdersEntity::class, (new EntityField('utm_term'))->setTypeVarchar(55)->setNullable());
        $this->migrateEntityField(OrdersEntity::class, (new EntityField('utm_content'))->setTypeVarchar(55)->setNullable());
        $this->migrateEntityField(OrdersEntity::class, (new EntityField('error_crm'))->setTypeVarchar(500)->setNullable());
    }

    public function update_1_2_0()
    {
        $this->migrateEntityField(OrderStatusEntity::class, (new EntityField(self::COLUMN_ID_CRM))->setTypeInt(1, true)->setDefault('0'));
        $this->migrateEntityField(OrderStatusEntity::class, (new EntityField(self::COLUMN_SEND_CRM))->setTypeTinyInt(1, true)->setDefault('0'));
    }
}
