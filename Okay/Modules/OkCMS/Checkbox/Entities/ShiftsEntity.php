<?php


namespace Okay\Modules\OkCMS\Checkbox\Entities;


use Okay\Core\Entity\Entity;

class ShiftsEntity extends Entity
{
    protected static $fields = [
        'id',
        'shift_id',
        'serial',
        'status',
        'z_report_id',
        'opened_at',
        'closed_at',
        'full_json_response'
    ];

    /*protected static $langFields = [
        'question',
        'answer',
    ];*/

    protected static $defaultOrderFields = [
        'opened_at DESC',
    ];

    protected static $table = '__okcms__checkbox_shifts';

    public function getActiveShift() {
        return $this->findOne(['opened' => 1]);
    }

    protected function filter__opened() {
        $this->select->where("(status='CREATED' OR status='OPENED')");
    }

    protected function filter__closed() {
        $this->select->where("(status='CLOSING' OR status='CLOSED')");
    }

    protected function filter__from_date($fromDate) {
        if (!empty($fromDate)) {
            $this->select->where('opened_at >= :from_date')
                ->bindValue('from_date', date('Y-m-d 00:00:00', strtotime($fromDate)));
        }
    }

    protected function filter__to_date($toDate) {
        if (!empty($toDate)) {
            $this->select->where('opened_at <= :to_date')
                ->bindValue('to_date', date('Y-m-d 23:59:59', strtotime($toDate)));
        }
    }
}