<?php

namespace Okay\Modules\SimplaMarket\KeyCRM;

use Okay\Core\Config;
use Okay\Core\Database;
use Okay\Core\EntityFactory;
use Okay\Core\Image;
use Okay\Core\OkayContainer\Reference\ServiceReference as SR;
use Okay\Core\QueryFactory;
use Okay\Core\Request;
use Okay\Core\Design;
use Okay\Core\Response;
use Okay\Core\Settings;
use Okay\Modules\OkayCMS\DeliveryFields\Helpers\DeliveryFieldsHelper;
use Okay\Modules\SimplaMarket\KeyCRM\Extenders\BackendExtender;
use Okay\Modules\SimplaMarket\KeyCRM\Extenders\FrontendExtender;
use Okay\Modules\SimplaMarket\KeyCRM\Helpers\KeyCRMHelper;
use Okay\Modules\SimplaMarket\KeyCRM\Helpers\RefererCRMHelper;
use Psr\Log\LoggerInterface;
use Snowplow\RefererParser\Parser;

return [
    BackendExtender::class => [
        'class' => BackendExtender::class,
        'arguments' => [
            new SR(Request::class),
            new SR(Design::class),
            new SR(EntityFactory::class),
            new SR(KeyCRMHelper::class),
        ],
    ],
    FrontendExtender::class => [
        'class' => FrontendExtender::class,
        'arguments' => [
            new SR(EntityFactory::class),
            new SR(KeyCRMHelper::class),
            new SR(RefererCRMHelper::class),
            new SR(Settings::class),
        ],
    ],

    KeyCRMHelper::class => [
        'class' => KeyCRMHelper::class,
        'arguments' => [
            new SR(EntityFactory::class),
            new SR(Config::class),
            new SR(Image::class),
            new SR(Request::class),
            new SR(Settings::class),
            new SR(LoggerInterface::class),
            new SR(QueryFactory::class),
            new SR(Database::class),
        ],
    ],
    RefererCRMHelper::class => [
        'class' => RefererCRMHelper::class,
        'arguments' => [
            new SR(EntityFactory::class),
            new SR(Request::class),
            new SR(Parser::class),
        ],
    ],
];