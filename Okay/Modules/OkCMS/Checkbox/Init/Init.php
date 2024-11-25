<?php


namespace Okay\Modules\OkCMS\Checkbox\Init;


use Okay\Admin\Helpers\BackendCategoriesHelper;
use Okay\Admin\Helpers\BackendMainHelper;
use Okay\Admin\Helpers\BackendOrdersHelper;
use Okay\Admin\Helpers\BackendProductsHelper;
use Okay\Admin\Requests\BackendPaymentsRequest;
use Okay\Admin\Requests\BackendProductsRequest;
use Okay\Core\Database;
use Okay\Core\Modules\AbstractInit;
use Okay\Core\Modules\EntityField;
use Okay\Core\QueryFactory;
use Okay\Core\ServiceLocator;
use Okay\Entities\ManagersEntity;
use Okay\Entities\OrdersEntity;
use Okay\Entities\PaymentsEntity;
use Okay\Entities\ProductsEntity;
use Okay\Modules\OkCMS\Checkbox\Entities\ReceiptsEntity;
use Okay\Modules\OkCMS\Checkbox\Entities\ShiftsEntity;
use Okay\Modules\OkCMS\Checkbox\Entities\TaxesEntity;
use Okay\Modules\OkCMS\Checkbox\Extenders\BackendExtender;

class Init extends AbstractInit
{

    const CHECKBOX_MANAGER_LOGIN = 'okcms__checkbox_login';
    const CHECKBOX_MANAGER_PASSWORD = 'okcms__checkbox_password';
    const CHECKBOX_MANAGER_LICENSE_KEY = 'okcms__checkbox_licenseKey';

    const CHECKBOX_PRODUCTS_TAXES_TABLE = '__okcms__checkbox_products_taxes';

    const CHECKBOX_PAYMENT_TYPE_FIELD = 'okcms__checkbox_type';

    public function install()
    {
        if (!is_dir('files/checkbox')) {
            mkdir('files/checkbox');
        }
        if (!is_dir('files/checkbox/reports')) {
            mkdir('files/checkbox/reports');
        }
        if (!is_dir('files/checkbox/receipts')) {
            mkdir('files/checkbox/receipts');
        }

        //$this->setModuleType(MODULE_TYPE_PAYMENT);
        $this->setBackendMainController('CheckboxAdmin');
        //$this->migrateEntityField(ManagersEntity::class, (new EntityField(self::CHECKBOX_MANAGER_LOGIN))->setTypeVarchar(255, true));
        //$this->migrateEntityField(ManagersEntity::class, (new EntityField(self::CHECKBOX_MANAGER_PASSWORD))->setTypeVarchar(255, true));
        //$this->migrateEntityField(ManagersEntity::class, (new EntityField(self::CHECKBOX_MANAGER_LICENSE_KEY))->setTypeVarchar(255, true));
        $this->migrateEntityTable(ShiftsEntity::class, [
            (new EntityField('id'))->setIndexPrimaryKey()->setTypeInt(11, false)->setAutoIncrement(),
            (new EntityField('shift_id'))->setTypeVarchar(64, false),
            (new EntityField('serial'))->setTypeInt(11, false)->setDefault('0'),
            (new EntityField('status'))->setTypeVarchar(32, false),
            (new EntityField('z_report_id'))->setTypeVarchar(64, false)->setDefault(''),
            (new EntityField('opened_at'))->setTypeTimestamp(true, null),
            (new EntityField('closed_at'))->setTypeTimestamp(true, null),
            (new EntityField('full_json_response'))->setTypeText(),
        ]);

        $this->migrateEntityTable(ReceiptsEntity::class, [
            (new EntityField('id'))->setIndexPrimaryKey()->setTypeInt(11, false)->setAutoIncrement(),
            (new EntityField('receipt_id'))->setTypeVarchar(64, false),
            (new EntityField('order_id'))->setTypeInt(11, false),
            (new EntityField('is_return'))->setTypeInt(1, false)->setDefault('0'),
            (new EntityField('created_at'))->setTypeTimestamp(true, null),
            (new EntityField('updated_at'))->setTypeTimestamp(true, null),
            (new EntityField('sended'))->setTypeDatetime(true),
            (new EntityField('full_json_response'))->setTypeText(),
        ]);

        $this->migrateEntityTable(TaxesEntity::class, [
            (new EntityField('id'))->setIndexPrimaryKey()->setTypeInt(11, false)->setAutoIncrement(),
            (new EntityField('code'))->setTypeInt(11, false)->setIndex(),
            (new EntityField('name'))->setTypeVarchar(255, false),
        ]);

        $this->migrateCustomTable(self::CHECKBOX_PRODUCTS_TAXES_TABLE, [
            (new EntityField('product_id'))->setTypeInt(11, false)->setIndex(),
            (new EntityField('tax_id'))->setTypeInt(11, false)->setIndex(),
        ]);

        $serviceLocator = ServiceLocator::getInstance();
        $queryFactory   = $serviceLocator->getService(QueryFactory::class);
        $db             = $serviceLocator->getService(Database::class);

        $sql = $queryFactory->newSqlQuery();
        $sql->setStatement("ALTER TABLE " . self::CHECKBOX_PRODUCTS_TAXES_TABLE . " ADD PRIMARY KEY (`product_id`,`tax_id`)");
        $db->query($sql);

        // Добавление в способ оплаты настройки CHECKBOX
        $this->migrateEntityField(
            PaymentsEntity::class,
            (new EntityField(self::CHECKBOX_PAYMENT_TYPE_FIELD))
                ->setTypeEnum(['CASH', 'CARD', 'CASHLESS'] , false)
                ->setDefault('CASH')
        );

    }

    public function init(){
        $this->addPermission('okcms__checkbox');

        $this->registerBackendController('CheckboxAdmin');
        $this->addBackendControllerPermission('CheckboxAdmin', 'okcms__checkbox');

        // Налоговые группы
        $this->registerBackendController('CheckboxTaxesAdmin'); // список
        $this->addBackendControllerPermission('CheckboxTaxesAdmin', 'okcms__checkbox');
        $this->registerBackendController('CheckboxTaxAdmin'); // одна
        $this->addBackendControllerPermission('CheckboxTaxAdmin', 'okcms__checkbox');

        // Отчеты
        $this->registerBackendController('CheckboxShiftsAdmin');
        $this->addBackendControllerPermission('CheckboxShiftsAdmin', 'okcms__checkbox');
        $this->registerBackendController('CheckboxReceiptsAdmin');
        $this->addBackendControllerPermission('CheckboxReceiptsAdmin', 'okcms__checkbox');


        $this->registerEntityField(PaymentsEntity::class, self::CHECKBOX_PAYMENT_TYPE_FIELD);

        //$this->registerEntityField(ManagersEntity::class, self::CHECKBOX_MANAGER_LOGIN);
        //$this->registerEntityField(ManagersEntity::class, self::CHECKBOX_MANAGER_PASSWORD);
        //$this->registerEntityField(ManagersEntity::class, self::CHECKBOX_MANAGER_LICENSE_KEY);

        $this->registerQueueExtension(
            ['class' => BackendMainHelper::class, 'method' => 'evensCounters'],
            ['class' => BackendExtender::class, 'method' => 'initCheckbox']
        );

        $this->registerQueueExtension(
            ['class' => BackendOrdersHelper::class, 'method' => 'findOrder'],
            ['class' => BackendExtender::class, 'method' => 'initCheckboxForOrder']
        );

        $this->registerQueueExtension(
            ['class' => BackendProductsHelper::class, 'method' => 'getProduct'],
            ['class' => BackendExtender::class, 'method' => 'getProduct']
        );
        $this->registerQueueExtension(
            ['class' => BackendProductsRequest::class, 'method' => 'postProduct'],
            ['class' => BackendExtender::class, 'method' => 'postProduct']
        );

        $this->registerQueueExtension(
            ['class' => BackendOrdersHelper::class, 'method' => 'findOrders'],
            ['class' => BackendExtender::class, 'method' => 'findOrders']
        );

        $this->registerQueueExtension(
            ['class' => BackendPaymentsRequest::class, 'method' => 'postPayment'],
            ['class' => BackendExtender::class, 'method' => 'postPayment']
        );

        $this->registerQueueExtension(
            ['class' => OrdersEntity::class, 'method' => 'markedPaid'],
            ['class' => BackendExtender::class, 'method' => 'orderMarkedPaid']
        );

        $this->registerQueueExtension(
            ['class' => BackendOrdersHelper::class, 'method' => 'updateOrderStatus'],
            ['class' => BackendExtender::class, 'method' => 'updateOrderStatus']
        );


        $this->addBackendBlock('main_custom_block_after_js', 'js_css_connection.tpl');
        $this->addBackendBlock('order_custom_block', 'checkbox_order_block.tpl');
        $this->addBackendBlock('product_relations', 'checkbox_product_taxes.tpl');
        $this->addBackendBlock('orders_list_name', 'checkbox_order_last_receipt.tpl');
        $this->addBackendBlock('payment_custom_block', 'checkbox_payment_type_block.tpl');


        $this->extendBackendMenu('okcms__left_checkbox', [
            'okcms__left_checkbox_settings' => ['CheckboxAdmin'],
            'okcms__left_checkbox_taxes' => ['CheckboxTaxesAdmin', 'CheckboxTaxAdmin'],
            'okcms__left_checkbox_receipts' => ['CheckboxReceiptsAdmin'],
            'okcms__left_checkbox_shifts' => ['CheckboxShiftsAdmin'],
        ], '<svg width="20" height="20" viewBox="0 0 24 15" xmlns="http://www.w3.org/2000/svg">
            <path d="M.918.04h22.164v14.92H.918V.04zM2.2 1.349v12.344h19.614V1.348H2.2zm1.616 5.939l3.968-3.608v7.216L3.816 7.287zm16.475 0l-4 3.636V3.651l4 3.636z" fill="currentColor" />
        </svg>');

    }
}