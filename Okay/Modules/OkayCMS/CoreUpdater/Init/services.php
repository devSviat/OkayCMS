<?php

namespace Okay\Modules\OkayCMS\CoreUpdater;

use Okay\Core\Config;
use Okay\Core\OkayContainer\Reference\ServiceReference as SR;
use Okay\Core\Settings;
use Okay\Modules\OkayCMS\CoreUpdater\Helpers\UpdateCheckHelper;
use Okay\Modules\OkayCMS\CoreUpdater\Helpers\UpdateDownloader;
use Okay\Modules\OkayCMS\CoreUpdater\Helpers\UpdateStatus;

return [
    UpdateCheckHelper::class => [
        'class' => UpdateCheckHelper::class,
        'arguments' => [
            new SR(Settings::class),
            new SR(Config::class),
        ],
    ],
    UpdateStatus::class => [
        'class' => UpdateStatus::class,
        'arguments' => [
            new SR(Settings::class),
        ],
    ],
    UpdateDownloader::class => [
        'class' => UpdateDownloader::class,
        'arguments' => [
            new SR(Config::class),
        ],
    ],
];
