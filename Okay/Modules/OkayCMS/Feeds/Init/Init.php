<?php

namespace Okay\Modules\OkayCMS\Feeds\Init;

use Okay\Core\Modules\AbstractInit;
use Okay\Core\Modules\EntityField;
use Okay\Modules\OkayCMS\Feeds\Entities\ConditionsEntity;
use Okay\Modules\OkayCMS\Feeds\Entities\FeedsEntity;

class Init extends AbstractInit
{
    const PERMISSION = 'okay_cms__feeds';
    const CONDITIONS_ENTITIES_RELATION_TABLE = '__okay_cms__feeds__conditions_entities';

    public function install()
    {
        $this->setModuleType(MODULE_TYPE_XML);

        $this->setBackendMainController('FeedsAdmin');

        $this->migrateEntityTable(FeedsEntity::class, [
            (new EntityField('id'))->setTypeInt(11, false)->setAutoIncrement(),
            (new EntityField('name'))->setTypeVarchar(100, false),
            (new EntityField('url'))->setTypeVarchar(100, false)->setIndexUnique(),
            (new EntityField('enabled'))->setTypeTinyInt(1, false)->setDefault(0),
            (new EntityField('preset'))->setTypeVarchar(255, false),
            (new EntityField('settings'))->setTypeMediumText(),
            (new EntityField('features_settings'))->setTypeMediumText(),
            (new EntityField('categories_settings'))->setTypeMediumText(),
            (new EntityField('position'))->setTypeInt(11)->setDefault(0)->setIndex()
        ]);

        $this->migrateEntityTable(ConditionsEntity::class, [
            (new EntityField('id'))->setTypeInt(11, false)->setAutoIncrement(),
            (new EntityField('feed_id'))->setTypeInt(11, false),
            (new EntityField('entity'))->setTypeVarchar(255, false),
            (new EntityField('type'))->setTypeEnum(['inclusion', 'exclusion']),
            (new EntityField('all_entities'))->setTypeTinyInt(1, false)->setDefault(0),
        ]);

        $entityIdField = (new EntityField('entity_id'))->setTypeInt(11, false);
        $this->migrateCustomTable(self::CONDITIONS_ENTITIES_RELATION_TABLE, [
            (new EntityField('condition_id'))->setTypeInt(11, false)->setIndexUnique(null, $entityIdField),
            $entityIdField,
        ]);
    }

    public function init()
    {
        $this->addPermission(self::PERMISSION);
        $this->registerBackendController('FeedsAdmin');
        $this->addBackendControllerPermission('FeedsAdmin', self::PERMISSION);
        $this->registerBackendController('FeedAdmin');
        $this->addBackendControllerPermission('FeedAdmin', self::PERMISSION);

        $this->extendBackendMenu('okay_cms__feeds__menu', [
            'okay_cms__feeds__menu' => ['FeedsAdmin', 'FeedAdmin'],
        ], '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 11a9 9 0 0 1 9 9"/><path d="M4 4a16 16 0 0 1 16 16"/><circle cx="5" cy="19" r="1"/></svg>');

        $this->extendUpdateObject('okay_cms__feed', self::PERMISSION, FeedsEntity::class);

    }
}