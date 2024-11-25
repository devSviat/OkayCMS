<?php


namespace Okay\Modules\SimplaMarket\KeyCRM\Entities;


use Okay\Core\Entity\Entity;

class KeyCRMOrderEntity extends Entity
{
    protected static $fields = [
        'id',
        'idCRM',
        'source_uuid',
        'global_source_uuid',
        'status_on_source',
        'source_id',
        'client_id',
        'grand_total',
        'total_discount',
        'margin_sum',
        'expenses_sum',
        'discount_amount',
        'discount_percent',
        'shipping_price',
        'payment_status',
        'created_at',
        'updated_at',
        'ordered_at',
    ];

    protected static $table = '__okaycms__keycrm__keycrm_order';
    protected static $tableAlias = 'key_crm_o';
}