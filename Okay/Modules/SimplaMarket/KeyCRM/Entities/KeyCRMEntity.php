<?php


namespace Okay\Modules\SimplaMarket\KeyCRM\Entities;


use Okay\Core\Entity\Entity;

class KeyCRMEntity extends Entity
{
    protected static $fields = [
        'id',
        'key',
        'value',
        'value2',
    ];

    protected static $table = '__okaycms__keycrm__keycrm';
    protected static $tableAlias = 'key_crm';
}