<?php

/**
 * Дата-провайдери в PHPUnit 10+ статичні й виконуються ще до setUp(), тож константи
 * ядра, на які вони посилаються, мусять бути на місці вже тут.
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../Okay/Core/config/constants.php';
require_once __DIR__ . '/../Okay/Core/config/functions.php';
