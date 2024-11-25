<?php


namespace Okay\Modules\SimplaMarket\TelegramNotify\ExtendsEntities;

use \Okay\Core\Modules\AbstractModuleEntityFilter;

class OrdersEntityExtend extends AbstractModuleEntityFilter
{
    public function filter_last_hour_orders($state){
        if ($state == true){
            $this->select->where('date < DATE_SUB(NOW() , INTERVAL 59 MINUTE)');
        }
    }
}