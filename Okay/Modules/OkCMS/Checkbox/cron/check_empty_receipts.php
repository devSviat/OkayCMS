<?php

use Okay\Core\Modules\Modules;
use Okay\Modules\OkCMS\Checkbox\Helpers\CheckboxMainHelper;

chdir(dirname(dirname(dirname(dirname(dirname(__DIR__))))));

require_once('vendor/autoload.php');

$DI = include 'Okay/Core/config/container.php';

/** @var Modules $modules */
$modules = $DI->get(Modules::class);
$modules->startEnabledModules();


$checkboxMainHelper = $DI->get(CheckboxMainHelper::class);

$checkboxMainHelper->checkEmptyReceipts();
exit;