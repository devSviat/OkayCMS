<?php

namespace Okay\Modules\OkCMS\Checkbox;

return [
    'OkCMS_Checkbox_createShift' => [
        'slug' => 'backend/checkbox/ajax/createShift',
        'to_front' => true,
        'params' => [
            'controller' => __NAMESPACE__ . '\Controllers\CheckboxAjaxController',
            'method' => 'createShift',
        ],
    ],
    'OkCMS_Checkbox_closeShift' => [
        'slug' => 'backend/checkbox/ajax/closeShift',
        'to_front' => true,
        'params' => [
            'controller' => __NAMESPACE__ . '\Controllers\CheckboxAjaxController',
            'method' => 'closeShift',
        ],
    ],
    'OkCMS_Checkbox_updateShift' => [
        'slug' => 'backend/checkbox/ajax/updateShift',
        'to_front' => true,
        'params' => [
            'controller' => __NAMESPACE__ . '\Controllers\CheckboxAjaxController',
            'method' => 'updateShift',
        ],
    ],
    'OkCMS_Checkbox_getShiftReport' => [
        'slug' => 'backend/checkbox/ajax/getShiftReport',
        'to_front' => true,
        'params' => [
            'controller' => __NAMESPACE__ . '\Controllers\CheckboxAjaxController',
            'method' => 'getShiftReport',
        ],
    ],
    'OkCMS_Checkbox_createReceipt' => [
        'slug' => 'backend/checkbox/ajax/createReceipt',
        'to_front' => true,
        'params' => [
            'controller' => __NAMESPACE__ . '\Controllers\CheckboxAjaxController',
            'method' => 'createReceipt',
        ],
    ],
    'OkCMS_Checkbox_getReceiptPdf' => [
        'slug' => 'backend/checkbox/ajax/getReceiptPdf/{$receiptId}',
        'to_front' => true,
        'params' => [
            'controller' => __NAMESPACE__ . '\Controllers\CheckboxAjaxController',
            'method' => 'getReceiptPdf',
        ],
    ],
    'OkCMS_Checkbox_cronShiftsCheck' => [
        'slug' => 'cron/checkbox/checkShifts',
        'params' => [
            'controller' => __NAMESPACE__ . '\Controllers\CheckboxCronController',
            'method' => 'checkShifts',
        ],
    ],
    'OkCMS_Checkbox_cronReceiptsCheckEmpty' => [
        'slug' => 'cron/checkbox/checkEmptyReceipts',
        'params' => [
            'controller' => __NAMESPACE__ . '\Controllers\CheckboxCronController',
            'method' => 'checkEmptyReceipts',
        ],
    ],
    'OkCMS_Checkbox_cronTest' => [
        'slug' => 'cron/checkbox/test',
        'params' => [
            'controller' => __NAMESPACE__ . '\Controllers\CheckboxCronController',
            'method' => 'test',
        ],
    ],
];