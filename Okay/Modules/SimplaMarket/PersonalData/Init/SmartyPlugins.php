<?php


use Okay\Core\Design;
use Okay\Core\OkayContainer\Reference\ServiceReference as SR;
use Okay\Modules\SimplaMarket\PersonalData\Plugins\ConfirmPersonalDataProcessingPlugin;

return [
    ConfirmPersonalDataProcessingPlugin::class => [
        'class' => ConfirmPersonalDataProcessingPlugin::class,
        'arguments' => [
            new SR(Design::class),
        ],
    ],
];