<?php


namespace Okay\Modules\SimplaMarket\EnhancedProductsSearch\Init;


use Okay\Modules\SimplaMarket\EnhancedProductsSearch\Controllers\EnhancedSearchController;

return [
    'EnhancedProductsSearch.ajaxSearch' => [
        'slug' => '/enhanced-search/search',
        'params' => [
            'controller' => EnhancedSearchController::class,
            'method' => 'ajaxSearch',
        ],
        'to_front' => true,
    ]
];