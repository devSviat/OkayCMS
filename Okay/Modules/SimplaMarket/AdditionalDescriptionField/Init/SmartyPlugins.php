<?php


namespace Okay\Modules\OkayCMS\Banners;


use Okay\Core\Design;
use Okay\Core\EntityFactory;
use Okay\Core\OkayContainer\Reference\ServiceReference as SR;
use Okay\Modules\SimplaMarket\AdditionalDescriptionField\Plugins\AdditionalFieldDataPlugin;

return [
    AdditionalFieldDataPlugin::class => [
        'class' => AdditionalFieldDataPlugin::class,
        'arguments' => [
            new SR(Design::class),
            new SR(EntityFactory::class),
        ],
    ],
];