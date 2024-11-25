<?php


namespace Okay\Modules\SimplaMarket\EnhancedProductsSearch;


use Okay\Core\Languages;
use Okay\Core\OkayContainer\Reference\ServiceReference as SR;
use Okay\Core\Request;
use Okay\Modules\SimplaMarket\EnhancedProductsSearch\Extenders\FrontExtender;

return [
    FrontExtender::class => [
        'class' => FrontExtender::class,
        'arguments' => [
            new SR(Request::class),
            new SR(Languages::class),
        ],
    ],
];