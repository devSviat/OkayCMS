<?php


namespace Okay\Modules\OkCMS\Checkbox\Entities;


use Okay\Core\Entity\Entity;

class ReceiptsEntity extends Entity
{
    protected static $fields = [
        'id',
        'receipt_id',
        'order_id',
        'is_return',
        'created_at',
        'updated_at',
        'full_json_response',
        'sended'
    ];

    /*protected static $langFields = [
        'question',
        'answer',
    ];*/

    protected static $defaultOrderFields = [
        'id',
    ];

    protected static $table = '__okcms__checkbox_receipts';

    protected function filter__from_date($fromDate) {
        if (!empty($fromDate)) {
            $this->select->where('created_at >= :from_date')
                ->bindValue('from_date', date('Y-m-d 00:00:00', strtotime($fromDate)));
        }
    }

    protected function filter__to_date($toDate) {
        if (!empty($toDate)) {
            $this->select->where('created_at <= :to_date')
                ->bindValue('to_date', date('Y-m-d 23:59:59', strtotime($toDate)));
        }
    }
}