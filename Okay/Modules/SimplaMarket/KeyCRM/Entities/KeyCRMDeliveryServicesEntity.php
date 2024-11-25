<?php

namespace Okay\Modules\SimplaMarket\KeyCRM\Entities;

use Okay\Core\Entity\Entity;

class KeyCRMDeliveryServicesEntity extends Entity
{
    protected static $fields = [
        'id',
        'idCRM',
        'name',
        'source_name',
        'alias',
    ];

    protected static $table = '__okaycms__keycrm__keycrm_delivery';
    protected static $tableAlias = 'key_crm_d';
}