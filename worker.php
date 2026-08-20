<?php

use Okay\Core\Kernel;

ini_set('display_errors', 'off');

require_once __DIR__ . '/vendor/autoload.php';

// Точка входу вітрини для FrankenPHP worker mode. Ті самі три виклики, що й в
// index.php, але boot() відпрацьовує раз на процес, а handle()/terminate() -
// на кожному запиті.
$kernel = new Kernel();
$kernel->boot();

// Запобіжник від витоків пам'яті: жодна бібліотека не зобов'язана бути
// чистою, тож процес перезапускається за лічильником, а не за симптомом.
$maxRequests = (int) ($_SERVER['MAX_REQUESTS'] ?? 0);

// Кожні стільки запитів робиться повний прохід по циклах.
$gcEvery = 100;

for ($handled = 0; $maxRequests === 0 || $handled < $maxRequests; $handled++) {
    try {
        $keepRunning = frankenphp_handle_request(static function () use ($kernel) {
            $kernel->handle();
        });
    } finally {
        // finally, а не наступний рядок: якби запит упав повз обробник у
        // handle(), стан лишився б неприбраним, і наступний покупець дістав би
        // привілеї попереднього. Падіння не має ставати витоком.
        $kernel->terminate();
    }

    // Не на кожному запиті: повний прохід по циклах коштує більше, ніж уся
    // економія від теплого процесу. Автоматичний збирач циклів PHP і без
    // цього працює, а MAX_REQUESTS лишається головним запобіжником.
    if ($handled % $gcEvery === 0) {
        gc_collect_cycles();
    }

    if ($keepRunning !== true) {
        break;
    }
}
