<?php


namespace Okay\Modules\SimplaMarket\OrderNotifyTelegram;


use Okay\Core\BackendTranslations;
use Okay\Core\Design;
use Okay\Core\EntityFactory;
use Okay\Core\OkayContainer\Reference\ServiceReference as SR;
use Okay\Core\Request;
use Okay\Core\Settings;
use Okay\Helpers\MainHelper;
use Okay\Helpers\OrdersHelper;
use Okay\Modules\SimplaMarket\TelegramNotify\Extenders\NotifyExtender;
use Okay\Modules\SimplaMarket\TelegramNotify\Helpers\TelegramHelper;
use Okay\Helpers\CommentsHelper;

return [
    TelegramHelper::class => [
        'class' => TelegramHelper::class,
        'arguments' => [
            new SR(Settings::class),
            new SR(Request::class),
            new SR(MainHelper::class),
            new SR(EntityFactory::class),
            new SR(BackendTranslations::class),
        ]
    ],

    NotifyExtender::class => [
        'class' => NotifyExtender::class,
        'arguments' => [
            new SR(TelegramHelper::class),
            new SR(EntityFactory::class),
            new SR(CommentsHelper::class),
            new SR(Design::class),
            new SR(OrdersHelper::class),
            new SR(Settings::class),
            new SR(BackendTranslations::class),
            new SR(Request::class),
        ]
    ]
];