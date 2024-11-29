<?php

namespace Okay\Modules\Sviat\WorkingHours\Init;

use Okay\Core\Design;
use Okay\Core\Languages;
use Okay\Core\EntityFactory;
use Okay\Core\FrontTranslations;
use Okay\Core\OkayContainer\Reference\ServiceReference as SR;
use Okay\Modules\Sviat\WorkingHours\Plugins\GetWorkingHoursPlugin;

return [
    GetWorkingHoursPlugin::class => [
        'class' => GetWorkingHoursPlugin::class,
        'arguments' => [
            new SR(Design::class),
            new SR(EntityFactory::class),
            new SR(Languages::class),
            new SR(FrontTranslations::class),
        ],
    ],
];
