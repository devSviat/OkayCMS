<?php


namespace Okay\Modules\SimplaMarket\KeyCRM\Entities;


use Okay\Core\Entity\Entity;

class KeyCRMStatusesEntity extends Entity
{
    protected static $fields = [
        'id',
        'idCRM',
        'name',
        'alias',
        'is_active',
        'group_id',
        'is_closing_order',
        'is_reserved',
        'expiration_period',
        'created_at',
        'updated_at',
    ];

    protected static $table = '__okaycms__keycrm__keycrm_statused';
    protected static $tableAlias = 'key_crm';
}