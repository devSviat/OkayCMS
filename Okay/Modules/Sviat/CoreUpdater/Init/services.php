<?php

namespace Okay\Modules\Sviat\CoreUpdater;

use Okay\Core\Config;
use Okay\Core\Design;
use Okay\Core\OkayContainer\Reference\ServiceReference as SR;
use Okay\Core\Release\CoreMigrator;
use Okay\Core\Settings;
use Okay\Core\Update\UpdateApplier;
use Okay\Core\Update\UpdateBackup;
use Okay\Core\Update\UpdateCheckHelper;
use Okay\Core\Update\UpdateDownloader;
use Okay\Core\Update\UpdateRunner;
use Okay\Core\Update\UpdateStatus;

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
