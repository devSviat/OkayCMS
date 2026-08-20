<?php

use Okay\Core\Kernel;

$startTime = microtime(true);

ini_set('display_errors', 'off');

require_once('vendor/autoload.php');

// Класична точка входу: один запит на процес. Ті самі три виклики в циклі -
// це worker.php.
$kernel = new Kernel();
$kernel->boot();
$kernel->handle($startTime);
$kernel->terminate();
