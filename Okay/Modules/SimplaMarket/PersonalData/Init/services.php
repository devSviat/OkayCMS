<?php

use Okay\Core\Response;
use Okay\Core\Request;
use Okay\Core\FrontTranslations;
use Okay\Core\OkayContainer\Reference\ServiceReference as SR;
use Okay\Core\Settings;
use Okay\Modules\SimplaMarket\PersonalData\Backend\Helpers\BackendSettingsHelper;
use Okay\Modules\SimplaMarket\PersonalData\Extensions\ValidateHelperExtension;

return [
    ValidateHelperExtension::class => [
        'class' => ValidateHelperExtension::class,
        'arguments' => [
            new SR(Response::class),
            new SR(Request::class),
            new SR(FrontTranslations::class),
        ],
    ],
    BackendSettingsHelper::class => [
        'class' => BackendSettingsHelper::class,
        'arguments' => [
            new SR(Settings::class),
            new SR(Request::class)
        ]
    ],
];