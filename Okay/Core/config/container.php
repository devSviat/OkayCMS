<?php

use Okay\Core\OkayContainer\OkayContainer;
use Okay\Core\Security\ManagerSessions;
use Okay\Core\Security\SessionNames;

require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/functions.php';
// Не require_once: повторне підключення (тести, вкладений вхід) отримало б
// true замість масиву. Самі конфіги лише повертають масиви.
$services   = require __DIR__ . '/services.php';
$parameters = require __DIR__ . '/parameters.php';

$container = OkayContainer::getInstance($services, $parameters);

// Тут, а не в кожному вході: цей файл підключає кожен, і вітрина визнає
// адміна лише після перевірки реєстром (без валідатора — «не адмін»).
SessionNames::setAdminSessionValidator(
    static fn(string $login, string $sessionId): bool => $container->get(ManagerSessions::class)->isValid($login, $sessionId)
);

return $container;
