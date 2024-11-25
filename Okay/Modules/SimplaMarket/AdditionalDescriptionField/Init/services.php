<?php


use Okay\Core\Request;
use Okay\Core\EntityFactory;
use Okay\Core\OkayContainer\Reference\ServiceReference as SR;
use Okay\Modules\SimplaMarket\AdditionalDescriptionField\Extensions\BackendProductsRequestExtension;

return [
    BackendProductsRequestExtension::class => [
        'class' => BackendProductsRequestExtension::class,
        'arguments' => [
            new SR(Request::class),
            new SR(EntityFactory::class),
        ],
    ],
];