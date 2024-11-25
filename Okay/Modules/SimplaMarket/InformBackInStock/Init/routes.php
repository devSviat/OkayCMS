<?php

namespace Okay\Modules\SimplaMarket\InformBackInStock;

return [
    'SimplaMarket.InformBackInStock' => [
        'slug' => '/inform-stock',
        'params' => [
            'controller' => __NAMESPACE__ . '\Controllers\InformBackInStockController',
            'method' => 'createNote',
        ],
        'to_front'=>true,
    ],
];