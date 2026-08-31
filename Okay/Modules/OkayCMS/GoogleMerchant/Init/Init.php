<?php


namespace Okay\Modules\OkayCMS\GoogleMerchant\Init;


use Okay\Admin\Helpers\BackendExportHelper;
use Okay\Admin\Helpers\BackendImportHelper;
use Okay\Core\Database;
use Okay\Core\Design;
use Okay\Core\Modules\AbstractInit;
use Okay\Core\Modules\EntityField;
use Okay\Core\QueryFactory;
use Okay\Core\ServiceLocator;
use Okay\Entities\BrandsEntity;
use Okay\Entities\CategoriesEntity;
use Okay\Entities\ProductsEntity;
use Okay\Modules\OkayCMS\GoogleMerchant\Entities\GoogleMerchantFeedsEntity;
use Okay\Modules\OkayCMS\GoogleMerchant\Entities\GoogleMerchantRelationsEntity;
use Okay\Modules\OkayCMS\GoogleMerchant\Extenders\BackendExtender;

class Init extends AbstractInit
{
    const TO_FEED_FIELD = 'to__okaycms__google_merchant';
    const FILTER_FEEDS  = 'okaycms__google_merchant__feeds';
    const PERMISSION    = 'okaycms__google_merchant';

    /**
     * Індекс під вибірки зв'язків за типом сутності — саме так їх читає
     * адмінка. Єдиний існуючий індекс починається з feed_id, тож для таких
     * запитів (і для DELETE у GoogleMerchantRelationsEntity) непридатний.
     */
    const RELATIONS_INDEX = 'idx_gm_relations_type_include_feed';

    public function install()
    {
        $this->setModuleType(MODULE_TYPE_XML);
        $this->setBackendMainController('GoogleMerchantAdmin');

        $this->migrateEntityTable(GoogleMerchantFeedsEntity::class, [
            (new EntityField('id'))->setTypeInt(11, false)->setAutoIncrement(),
            (new EntityField('name'))->setTypeVarchar(100, false),
            (new EntityField('url'))->setTypeVarchar(100, false)->setIndexUnique(),
            (new EntityField('enabled'))->setTypeTinyInt(1, false)->setDefault(0),
        ]);

        $entityTypeField = (new EntityField('entity_type'))->setTypeEnum(['product', 'category', 'brand'], false);
        $includeField = (new EntityField('include'))->setTypeTinyInt(1, false);
        $entityIdField = (new EntityField('entity_id'))->setTypeInt(11, false);
        $this->migrateEntityTable(GoogleMerchantRelationsEntity::class, [
            (new EntityField('id'))->setTypeInt(11, false)->setAutoIncrement(),
            (new EntityField('feed_id'))->setTypeInt(11, false)->setIndexUnique(null, $entityTypeField, $includeField, $entityIdField),
            $entityIdField,
            $entityTypeField,
            $includeField,
        ]);

        $this->createRelationsIndex();
    }

    /**
     * 1.0.1 — індекс на таблицю зв'язків.
     */
    public function update_1_0_1()
    {
        $this->createRelationsIndex();
    }

    private function createRelationsIndex()
    {
        // В update_x_y_z() немає DI, тому сервіси беремо через ServiceLocator.
        $SL = ServiceLocator::getInstance();

        /** @var QueryFactory $queryFactory */
        $queryFactory = $SL->getService(QueryFactory::class);

        /** @var Database $db */
        $db = $SL->getService(Database::class);

        // CREATE INDEX IF NOT EXISTS підтримує MariaDB (стек проєкту), тож
        // метод безпечно викликати і з install(), і з update_1_0_1().
        $sql = $queryFactory->newSqlQuery();
        $sql->setStatement(
            'CREATE INDEX IF NOT EXISTS ' . self::RELATIONS_INDEX
            . ' ON ' . GoogleMerchantRelationsEntity::getTable() . ' (entity_type, `include`, feed_id)'
        );

        $db->query($sql);
    }

    public function init()
    {
        $this->addPermission(self::PERMISSION);
        $this->registerBackendController('GoogleMerchantAdmin');
        $this->addBackendControllerPermission('GoogleMerchantAdmin', self::PERMISSION);

        $this->addBackendBlock('import_fields_association',
            'import_fields_association.tpl',
            function(
                GoogleMerchantFeedsEntity $feedsEntity,
                Design                    $design
            ) {
                $design->assign('googleFeeds', $feedsEntity->find());
            }
        );

        $this->registerQueueExtension(
            [BackendImportHelper::class, 'importItem'],
            [BackendExtender::class, 'importItem']
        );

        $this->registerQueueExtension(
            [BackendImportHelper::class, 'parseProductData'],
            [BackendExtender::class, 'parseProductData']
        );

        $this->registerChainExtension(
            [BackendExportHelper::class, 'getColumnsNames'],
            [BackendExtender::class, 'extendExportColumnsNames']
        );

        $this->registerChainExtension(
            [BackendExportHelper::class, 'setUp'],
            [BackendExtender::class, 'extendFilter']
        );

        $this->registerChainExtension(
            [BackendImportHelper::class, 'getModulesColumnsNames'],
            [BackendExtender::class, 'getModulesColumnsNames']
        );
        
        $this->registerEntityFilter(
            ProductsEntity::class,
            self::FILTER_FEEDS,
            \Okay\Modules\OkayCMS\GoogleMerchant\ExtendsEntities\ProductsEntity::class,
            self::FILTER_FEEDS
        );
    }
    
}