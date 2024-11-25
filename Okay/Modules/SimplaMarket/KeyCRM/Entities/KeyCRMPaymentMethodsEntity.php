<?php


namespace Okay\Modules\SimplaMarket\KeyCRM\Entities;


use Okay\Core\Entity\Entity;

class KeyCRMPaymentMethodsEntity extends Entity
{
    protected static $fields = [
        'id',
        'idCRM',
        'name',
        'alias',
        'is_active',
    ];

    protected static $table = '__okaycms__keycrm__keycrm_payment';
    protected static $tableAlias = 'key_crm_p';
}