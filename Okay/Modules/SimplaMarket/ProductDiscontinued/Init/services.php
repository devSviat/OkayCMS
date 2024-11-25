<?php


namespace Okay\Modules\SimplaMarket\ProductDiscontinued\Init;

use Okay\Core\OkayContainer\Reference\ServiceReference as SR;
use Okay\Core\Request;
use Okay\Modules\SimplaMarket\ProductDiscontinued\Extensions\BackendExtension;

return [
    BackendExtension::class => [
        'class' => BackendExtension::class,
        'arguments' => [
            new SR(Request::class)
        ]
    ],
];