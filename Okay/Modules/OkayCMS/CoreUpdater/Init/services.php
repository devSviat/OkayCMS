<?php

namespace Okay\Modules\OkayCMS\CoreUpdater;

use Okay\Core\Config;
use Okay\Core\Design;
use Okay\Core\OkayContainer\Reference\ServiceReference as SR;
use Okay\Core\Release\CoreMigrator;
use Okay\Core\Settings;
use Okay\Modules\OkayCMS\CoreUpdater\Helpers\UpdateApplier;
use Okay\Modules\OkayCMS\CoreUpdater\Helpers\UpdateBackup;
use Okay\Modules\OkayCMS\CoreUpdater\Helpers\UpdateCheckHelper;
use Okay\Modules\OkayCMS\CoreUpdater\Helpers\UpdateDownloader;
use Okay\Modules\OkayCMS\CoreUpdater\Helpers\UpdateRunner;
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
    UpdateBackup::class => [
        'class' => UpdateBackup::class,
        'arguments' => [
            new SR(Config::class),
        ],
    ],
    UpdateApplier::class => [
        'class' => UpdateApplier::class,
    ],
    UpdateRunner::class => [
        'class' => UpdateRunner::class,
        'arguments' => [
            new SR(UpdateCheckHelper::class),
            new SR(UpdateStatus::class),
            new SR(UpdateDownloader::class),
            new SR(UpdateBackup::class),
            new SR(UpdateApplier::class),
            new SR(CoreMigrator::class),
            new SR(Settings::class),
            new SR(Config::class),
            new SR(Design::class),
        ],
    ],
];
