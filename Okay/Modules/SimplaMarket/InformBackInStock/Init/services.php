<?php

namespace Okay\Modules\SimplaMarket\InformBackInStock;

use Okay\Core\BackendTranslations;
use Okay\Core\Design;
use Okay\Core\EntityFactory;
use Okay\Core\FrontTranslations;
use Okay\Core\Languages;
use Okay\Core\Notify;
use Okay\Core\Request;
use Okay\Core\OkayContainer\Reference\ParameterReference as PR;
use Okay\Core\OkayContainer\Reference\ServiceReference as SR;
use Okay\Core\Settings;
use Okay\Modules\SimplaMarket\InformBackInStock\Core\InformBackInStockNotify;
use Okay\Modules\SimplaMarket\InformBackInStock\Extenders\BackendExtender;


return [
    BackendExtender::class => [
        'class' => BackendExtender::class,
        'arguments' => [
            new SR(Request::class),
            new SR(EntityFactory::class),
            new SR(Design::class),
            new SR(BackendTranslations::class),
            new SR(Settings::class),
            new SR(InformBackInStockNotify::class),
        ]
    ],

    InformBackInStockNotify::class => [
        'class' => InformBackInStockNotify::class,
        'arguments' => [
            new SR(Notify::class),
            new SR(Settings::class),
            new SR(EntityFactory::class),
            new SR(BackendTranslations::class),
            new SR(Design::class),
            new PR('root_dir'),
            new SR(Languages::class),
            new SR(FrontTranslations::class),
        ],
    ],


];