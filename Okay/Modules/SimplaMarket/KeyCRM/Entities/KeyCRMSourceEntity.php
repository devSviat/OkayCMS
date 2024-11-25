<?php


namespace Okay\Modules\SimplaMarket\KeyCRM\Entities;


use Okay\Core\Entity\Entity;

class KeyCRMSourceEntity extends Entity
{
    protected static $fields = [
        'id',
        'idCRM',
        'name',
        'alias',
        'driver',
        'source_name',
        'source_uuid',
        'currency_code',
        'source',
        'expense_type_id',
        'with_expenses',
        'created_at',
        'updated_at',
    ];

    protected static $table = '__okaycms__keycrm__keycrm_source';
    protected static $tableAlias = 'key_crm';
}