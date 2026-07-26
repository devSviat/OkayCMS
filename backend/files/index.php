<?php

use Okay\Core\Response;
use Okay\Core\EntityFactory;
use Okay\Core\Security\BackendFileDownloadPolicy;
use Okay\Core\Security\Filemanager\PathResolver;
use Okay\Core\Security\SessionNames;
use Okay\Entities\ManagersEntity;
use Okay\Core\Modules\Modules;

chdir('../..');

require_once('vendor/autoload.php');

SessionNames::startBackend();

$DI = include 'Okay/Core/config/container.php';

/** @var Modules $modules */
$modules = $DI->get(Modules::class);
$modules->startEnabledModules();

$modules->registerSmartyPlugins();

/** @var Response $response */
$response = $DI->get(Response::class);

/** @var EntityFactory $entityFactory */
$entityFactory = $DI->get(EntityFactory::class);

/** @var ManagersEntity $managersEntity */
$managersEntity = $entityFactory->get(ManagersEntity::class);
$manager = empty($_SESSION['admin']) ? null : $managersEntity->get($_SESSION['admin']);

if (empty($manager)) {
    exit();
}

$file   = isset($_GET['file']) && is_string($_GET['file']) ? $_GET['file'] : '';
$folder = isset($_GET['folder']) && is_string($_GET['folder']) ? $_GET['folder'] : '';
$ext    = isset($_GET['ext']) && is_string($_GET['ext']) ? $_GET['ext'] : '';

// Раньше фильтровалось только имя файла, а folder и ext приходили из GET
// как есть — folder выводил за пределы backend/files/.
$requiredPermission = (new BackendFileDownloadPolicy())->permissionFor($folder, $file, $ext);

if ($requiredPermission === null) {
    exit();
}

// Скачивание привязано к конкретному праву, а не просто к наличию сессии.
if (empty($manager->permissions) || !in_array($requiredPermission, (array)$manager->permissions, true)) {
    exit();
}

$ext = strtolower($ext);
$file = (new PathResolver(__DIR__))->resolve($folder . '/' . $file . '.' . $ext);

if ($file === null || !is_file($file)) {
    exit();
}

if ($ext == 'csv') {
    $response->addHeader('Content-Description: File Transfer');
    $response->addHeader('Content-Type: application/octet-stream');
    $response->addHeader('Content-Disposition: attachment; filename='.basename($file));
    $response->addHeader('Expires: 0');
    $response->addHeader('Cache-Control: must-revalidate');
    $response->addHeader('Pragma: public');
    $response->addHeader('Content-Length: ' . filesize($file));
    $response->addHeader('Content-Description: File Transfer');
    $response->sendHeaders();
    readfile($file);
    exit();
} elseif ($ext == 'png' || $ext == 'jpg' || $ext == 'jpeg' || $ext == 'gif' || $ext == 'tif' || $ext == 'bmp') {
    $response->setContent(file_get_contents($file), RESPONSE_IMAGE);
    $response->sendContent();
}

exit();