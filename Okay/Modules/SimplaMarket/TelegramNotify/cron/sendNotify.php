<?php
use Okay\Core\Modules\Modules;
use Okay\Modules\SimplaMarket\TelegramNotify\Helpers\TelegramHelper;
use Okay\Core\Request;

chdir(dirname(__DIR__ ,5));

require_once('vendor/autoload.php');

$DI = include 'Okay/Core/config/container.php';

/** @var Modules $modules */
$modules = $DI->get(Modules::class);
$modules->startEnabledModules();

$requests = $DI->get(Request::class);
$args     = $requests->getArgv();

$telegramHelper = $DI->get(TelegramHelper::class);
$telegramHelper->checkUnpaidOrders($args["root_url"]);
