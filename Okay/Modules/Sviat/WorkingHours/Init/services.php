<?php

namespace Okay\Modules\Sviat\WorkingHours\Init;

use Okay\Core\Request;
use Okay\Core\Managers;
use Okay\Core\Settings;
use Okay\Core\QueryFactory;
use Okay\Core\EntityFactory;
use Okay\Core\OkayContainer\Reference\ServiceReference as SR;
use Okay\Modules\Sviat\WorkingHours\Backend\Controllers\AdminWorkingHours;

return [
    AdminWorkingHours::class => [
        'class' => AdminWorkingHours::class,
        'arguments' => [
            new SR(EntityFactory::class),
            new SR(Managers::class),
            new SR(Settings::class),
            new SR(Request::class),
            new SR(QueryFactory::class),
        ],
    ],
];
