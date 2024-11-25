<?php


use Okay\Core\Design;
use Okay\Modules\SimplaMarket\InformBackInStock\Plugins\ReportInStockPlugin;
use Okay\Core\OkayContainer\Reference\ParameterReference as PR;
use Okay\Core\OkayContainer\Reference\ServiceReference as SR;

return [
   ReportInStockPlugin::class => [
        'class' => ReportInStockPlugin::class,
        'arguments' => [
            new SR(Design::class)
        ],
    ],
];