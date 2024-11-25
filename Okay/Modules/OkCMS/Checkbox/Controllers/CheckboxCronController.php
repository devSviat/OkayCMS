<?php


namespace Okay\Modules\OkCMS\Checkbox\Controllers;


use Okay\Controllers\AbstractController;
use Okay\Modules\OkCMS\Checkbox\Helpers\CheckboxMainHelper;


class CheckboxCronController extends AbstractController
{
    public function checkShifts(CheckboxMainHelper  $checkboxMainHelper){
        $checkboxMainHelper->cronCheckShifts();
        // т.к. это крон-задача - просто завершаем выполнение скрипта без какого-либо вывода
        exit;
    }

    public function checkEmptyReceipts(CheckboxMainHelper  $checkboxMainHelper){
        $checkboxMainHelper->checkEmptyReceipts();
        // т.к. это крон-задача - просто завершаем выполнение скрипта без какого-либо вывода
        exit;
    }

    public function test(CheckboxMainHelper  $checkboxMainHelper) {
        //var_dump($checkboxMainHelper->getAllTaxes());
        exit;
    }
}