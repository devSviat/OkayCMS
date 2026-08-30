<?php

$startTime = microtime(true);

use Okay\Core\Router;
use Okay\Core\Request;
use Okay\Core\Response;
use Okay\Core\Config;
use Okay\Core\DebugBar\DebugBar;
use Okay\Core\Modules\Modules;
use Okay\Core\OkayContainer\OkayContainer;
use Psr\Log\LoggerInterface;

ini_set('display_errors', 'off');

require_once('vendor/autoload.php');

// Оновлення ядра: вітрина закрита, health-check проходить за токеном
// з прапорця (див. CoreUpdater/MaintenanceMode). До DI й БД навмисно —
// сторінка технічних робіт не має права впасти через них.
// backend/index.php свідомо без цього гейта: адмінка мусить лишатись
// живою, щоб бачити прогрес і статус оновлення.
// file_exists() йде першим і без нього — єдина ціна цього блоку на
// кожному хіті вітрини; class_exists (з можливим автозавантаженням)
// рахується лише коли прапорець реально лежить на диску. Шлях зібраний
// літералом, а не через MaintenanceMode::flagPath(__DIR__), бо клас тут
// ще не гарантовано автозавантажений — тримати його синхронним з
// контрактом flagPath() ('config/.maintenance') покриває
// MaintenanceModeTest::testFlagPathContractStaysConfigDotMaintenance.
$maintenanceFlag = __DIR__ . '/config/.maintenance';
if (file_exists($maintenanceFlag)) {
    // Прапорець лежить, а класу немає — рівно те, що видно посеред
    // apply_files, коли autoload уже не знаходить ні старий, ні новий файл.
    // Провалитись повз гейт тут означало б відкрити вітрину над
    // напівзастосованим ядром, тому 503 віддається інлайном, без єдиної
    // залежності, яка могла б не завантажитись.
    if (!class_exists(\Okay\Modules\OkayCMS\CoreUpdater\Helpers\MaintenanceMode::class)) {
        http_response_code(503);
        header('Retry-After: 120');
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="uk"><head><meta charset="utf-8">'
            . '<title>Технічні роботи</title></head><body>'
            . '<h1>503 Сервіс тимчасово недоступний</h1>'
            . '<p>Триває оновлення. Спробуйте, будь ласка, за кілька хвилин.</p>'
            . '</body></html>';
        exit;
    }

    // Суперглобалі можуть віддати масив (?core_updater_token[]=x) —
    // normalizeToken() зводить це до null замість TypeError у allowsRequest().
    $providedToken = \Okay\Modules\OkayCMS\CoreUpdater\Helpers\MaintenanceMode::normalizeToken(
        $_SERVER['HTTP_X_CORE_UPDATER_TOKEN'] ?? ($_GET['core_updater_token'] ?? null)
    );

    if (!\Okay\Modules\OkayCMS\CoreUpdater\Helpers\MaintenanceMode::allowsRequest($maintenanceFlag, $providedToken)) {
        http_response_code(503);
        header('Retry-After: 120');
        echo \Okay\Modules\OkayCMS\CoreUpdater\Helpers\MaintenanceMode::renderPage();
        exit;
    }

    // Health-check пробою UpdateRunner (спек §8.11): якщо ми тут — токен
    // уже пройшов allowsRequest() вище. Відповідь без DI й без БД: клас
    // читається через ReflectionClass, конструктор не викликається —
    // доводить лише, що autoload підхопив нові core-файли після apply.
    if (isset($_GET['core_updater_health'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'forkVersion' => (new \ReflectionClass(\Okay\Core\Config::class))->getDefaultProperties()['forkVersion'],
        ]);
        exit;
    }
}

// Має відбутися до старту сесії вітрини: одночасно активною може бути
// лише одна сесія, а тут ми на мить читаємо бекендову.
\Okay\Core\Security\SessionNames::isAdmin();
\Okay\Core\Security\SessionNames::startFrontend();

/** @var OkayContainer $DI */
$DI = include 'Okay/Core/config/container.php';

/** @var Config $config Конфигурируем в конструкторе сервиса параметры системы */
$config = $DI->get(Config::class);

// Панель відладки вмикається з конфіга (debug_bar у config.local.php), а не
// хардкодом `if (false)`, який тут стояв. Конфіг доводиться читати раніше за
// init(): без нього нема чим керувати.
if ($config->get('debug_bar') == true && $config->get('debug_mode') == true) {
    DebugBar::init();
}
DebugBar::startMeasure('init', 'System init');

try {
    /** @var Router $router */
    $router = $DI->get(Router::class);
    
    // Редирект с повторяющихся слешей
    $uri = str_replace(Request::getDomainWithProtocol(), '', Request::getCurrentUrl());
    if (($destination = preg_replace('~//+~', '/', $uri, -1, $countReplace)) && $countReplace > 0) {
        Response::redirectTo($destination, 301);
    }
    $router->resolveCurrentLanguage();

    if ($config->get('debug_mode') == true) {
        ini_set('display_errors', 'on');
        error_reporting(E_ALL);
    }
    
    /** @var Response $response */
    $response = $DI->get(Response::class);
    
    /** @var Request $request */
    $request = $DI->get(Request::class);
    // Установим время начала выполнения скрипта
    $request->setStartTime($startTime);

    if (isset($_GET['logout'])) {
        unset($_SESSION['admin']);
        unset($_SESSION['modules_request_timeout']);
        unset($_SESSION['support_request_timeout']);
        unset($_SESSION['last_version_data']);
        setcookie('admin_login', '', [
            'expires'  => time() - 100,
            'path'     => '/',
            'secure'   => \Okay\Core\Security\SessionNames::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        
        $response->redirectTo($request->getRootUrl());
    }
    
    /** @var Modules $modules */
    $modules = $DI->get(Modules::class);
    DebugBar::stopMeasure('init');
    $modules->startEnabledModules();

    $router->run();

    if ($response->getContentType() == RESPONSE_HTML) {
        // Отладочная информация
        print "<!--\r\n";
        $timeEnd = microtime(true);
        $execTime = $timeEnd - $startTime;

        if (function_exists('memory_get_peak_usage')) {
            print "memory peak usage: " . memory_get_peak_usage() . " bytes\r\n";
        }
        print "page generation time: " . $execTime . " seconds\r\n";
        print "-->";
    }

} catch (\Exception $e) {
    
    /** @var LoggerInterface $logger */
    $logger = $DI->get(LoggerInterface::class);
    
    $message = $e->getMessage() . PHP_EOL . $e->getTraceAsString();
    header($_SERVER['SERVER_PROTOCOL'].' 500 Internal Server Error');
    if ($config->get('debug_mode') == true) {
        print $message;
    } else {
        $logger->critical($message);
    }
}
