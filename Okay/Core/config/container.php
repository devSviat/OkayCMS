<?php

use Okay\Core\OkayContainer\OkayContainer;

require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/functions.php';
// require, а не require_once: на друге включення в тому самому процесі
// require_once віддає true, а не масив, і контейнер не мав би з чого зібратись.
// Обидва файли лише будують масив, тож повторне виконання безпечне.
$services   = require __DIR__ . '/services.php';
$parameters = require __DIR__ . '/parameters.php';

return OkayContainer::getInstance($services, $parameters);
