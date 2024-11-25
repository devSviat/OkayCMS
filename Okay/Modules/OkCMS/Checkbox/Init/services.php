<?php


namespace Okay\Modules\OkCMS\Checkbox;


use Okay\Modules\OkCMS\Checkbox\Extenders\BackendExtender;
use Okay\Modules\OkCMS\Checkbox\Helpers\CheckboxMainHelper;

return [
    CheckboxMainHelper::class => [
        'class' => CheckboxMainHelper::class,
        'arguments' => [
        ],
    ],
    BackendExtender::class => [
        'class' => BackendExtender::class,
        'arguments' => [
        ],
    ],
];