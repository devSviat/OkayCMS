<?php

use Okay\Core\BackendTranslations;
use Okay\Core\Design;
use Okay\Core\Modules\Module;
use Okay\Core\Modules\Modules;
use Okay\Entities\ManagersEntity;
use Okay\Core\EntityFactory;
use Okay\Core\ServiceLocator;
use Okay\Core\Request;
use Okay\Core\Response;
use Okay\Core\Managers;
use Okay\Core\ManagerMenu;
use Okay\Core\Config;
use Okay\Core\Entity\Entity;
use Okay\Core\Languages;
use Okay\Core\Security\SessionNames;
use Okay\Admin\Helpers\BackendModulesHelper;

ini_set('display_errors', 'off');

//ini_set('display_errors', 'on');
//error_reporting(E_ALL);

chdir('..');

require_once('vendor/autoload.php');

// Без гейта техробіт з index.php: під час оновлення ядра адмінка мусить
// лишатись живою, щоб бачити прогрес і статус (CoreUpdater/MaintenanceMode).
$DI = include 'Okay/Core/config/container.php';

/**
 * Конфигурируем в конструкторе сервиса параметры системы
 *
 * @var Config $config
 */
$config = $DI->get(Config::class);

// Засекаем время
$time_start = microtime(true);
ini_set('session.gc_maxlifetime', 86400); // 86400 = 24 часа
// cookie_lifetime задається в SessionNames::cookieParams()
\Okay\Core\Security\SessionNames::startBackend();

// Шаблони друкують це значення як {$smarty.session.id} у полі session_id.
// Тепер це окремий CSRF-токен, а не ідентифікатор сесії.
$_SESSION['id'] = \Okay\Core\Security\AdminCsrfToken::get();

if ($config->get('debug_mode') == true) {
    ini_set('display_errors', 'on');
    error_reporting(E_ALL);
}

/** @var Request $request */
$request = $DI->get(Request::class);

// Вихід менеджера. Тільки POST і тільки з валідним CSRF-токеном; сам вихід
// має відбуватись тут, бо бекендова сесія активна лише в цьому вході -
// вітринний ?logout її не бачить із моменту розділення просторів сесій.
if ($request->isPost() && $request->post('logout') !== null && $request->checkSession()) {
    \Okay\Core\Security\SessionNames::destroyBackend();
    \Okay\Core\Security\SessionNames::deleteCookie('admin_login');

    header('Location: ' . $request->getRootUrl() . '/backend/index.php?controller=AuthAdmin');
    exit;
}

/** @var Languages $languages */
$languages = $DI->get(Languages::class);

$postLangId = $request->post('lang_id', 'integer');
$adminLangId = ($postLangId ? $postLangId : $request->get('lang_id', 'integer'));

if ($adminLangId) {
    $_SESSION['admin_lang_id'] = $adminLangId;
}

if (!empty($_SESSION['admin_lang_id'])) {
    $languages->setLangId((int)$_SESSION['admin_lang_id']);
} else {
    $_SESSION['admin_lang_id'] = $languages->getLangId();
}

// Оновлюємо кеш даних інформації по терміну доступу до оновлень модулів.
// Тільки для залогіненого менеджера: інакше анонімне звернення до /backend/
// змушує сервер сходити на маркетплейс і записати два рядки в ok_settings.
// $manager резолвиться нижче, а тут достатньо того самого $_SESSION['admin'] -
// перенести виклик під нього не можна, бо кеш читається на 40 рядків нижче.
/** @var BackendModulesHelper $backendModulesHelper */
$backendModulesHelper = $DI->get(BackendModulesHelper::class);
if (!empty($_SESSION['admin'])) {
    $backendModulesHelper->updateModulesAccessExpiresCache();
}

/** @var BackendTranslations $backendTranslations */
$backendTranslations = $DI->get(BackendTranslations::class);

/** @var Response $response */
$response = $DI->get(Response::class);

/** @var Managers $managers */
$managers = $DI->get(Managers::class);

/** @var ManagerMenu $managerMenu */
$managerMenu = $DI->get(ManagerMenu::class);

/** @var EntityFactory $entityFactory */
$entityFactory = $DI->get(EntityFactory::class);

/** @var Modules $modules */
$modules = $DI->get(Modules::class);

/** @var Design $design */
$design = $DI->get(Design::class);

/** @var Module $module */
$module = $DI->get(Module::class);

/** @var BackendModulesHelper $modulesHelper */
$modulesHelper = $DI->get(BackendModulesHelper::class);

$module->setModulesExpires(
    $modulesHelper->getModulesAccessExpiresFromCache()
);

// Запускаем все модули
$modules->startAllModules();

$modules->registerSmartyPlugins();
$modules->indexingNotInstalledModules();

$smartyPlugins = include_once 'Okay/Core/SmartyPlugins/SmartyPlugins.php';

// SL будем использовать только для получения сервисов, которые запросили для контроллера
$serviceLocator = ServiceLocator::getInstance();

/** @var ManagersEntity $managersEntity */
$managersEntity = $entityFactory->get(ManagersEntity::class);

$response->addHeader('Cache-Control: no-cache, must-revalidate');
$response->addHeader('Expires: -1');
$response->addHeader('Pragma: no-cache');

// Берем название модуля из get-запроса
$backendControllerName = $request->get('controller');
$backendControllerName = preg_replace("/[^A-Za-z0-9.@]+/", "", (string)$backendControllerName);
$routeParams = explode('@', $backendControllerName, 2);
$backendControllerName = $routeParams[0];
$methodName = (!empty($routeParams[1]) ? $routeParams[1] : 'fetch');

$manager = null;
if (!empty($_SESSION['admin'])) {
    $manager = $managersEntity->get($_SESSION['admin']);
}

if (!$manager && $backendControllerName != 'AuthAdmin') {
    $_SESSION['before_auth_url'] = $request->getBasePathWithDomain();
    $response->redirectTo($request->getRootUrl() . '/backend/index.php?controller=AuthAdmin');
}

if ($manager && $backendControllerName == 'AuthAdmin') {
    $response->redirectTo($request->getRootUrl() . '/backend/index.php');
}

$design->setCompiledDir('backend/design/compiled');
$design->setTemplatesDir('backend/design/html');
$modulesBackendControllers = $modules->getBackendControllers();

foreach ($modulesBackendControllers as $backendController) {
    $managerMenu->addCommonModuleController($backendController);
}

if (!empty($manager)) {
    $backendTranslations->initTranslations($manager->lang);
} else {
    // Менеджера ще немає, тож мову сторінки входу дають, за спаданням
    // пріоритету: попередній вхід із цього браузера, головна мова магазину,
    // решта його ввімкнених мов за порядком. Останню опору додає resolveLang().
    $preferred = [];

    if (isset($_COOKIE[SessionNames::ADMIN_LANG_COOKIE])) {
        $preferred[] = $_COOKIE[SessionNames::ADMIN_LANG_COOKIE];
    }

    foreach ($languages->getAllLanguages() as $language) {
        // getAllLanguages() віддає і вимкнені мови - на вітрині їх немає,
        // тож і сторінці входу вони не підходять.
        if (!empty($language->enabled)) {
            $preferred[] = $language->label;
        }
    }

    $backendTranslations->initTranslations($backendTranslations->resolveLang($preferred));
}

$design->assign('btr', $backendTranslations);

if (($controllerParams = $module->getBackendControllerParams($backendControllerName)) && in_array($backendControllerName, $modulesBackendControllers)) {

    $vendor = $controllerParams['vendor'];
    $moduleName = $controllerParams['module'];
    $controllerName = $controllerParams['controller'];
    
    $design->setModuleTemplatesDir($module->getModuleDirectory($vendor, $moduleName) . 'Backend/design/html');
    $design->useModuleDir();
    $controllerName = $module->getBackendControllersNamespace($vendor, $moduleName) . '\\' . $controllerName;
} else {
    
    $backendControllerName = preg_replace("/[^A-Za-z0-9]+/", "", $backendControllerName);

    // Всегда открываем контроллер, который стоит в меню первым
    if (!class_exists('\\Okay\\Admin\\Controllers\\' . $backendControllerName)) {
        if ($menu = $managerMenu->getMenu($manager)) {
            $subMenu = reset($menu);
            $backendControllerName = reset($subMenu);
            $backendControllerName = $backendControllerName['controller'];
        }
    }
    if (($controllerParams = $module->getBackendControllerParams($backendControllerName)) && in_array($backendControllerName, $modulesBackendControllers)) {

        $vendor = $controllerParams['vendor'];
        $moduleName = $controllerParams['module'];
        $controllerName = $controllerParams['controller'];

        $design->useModuleDir();
        $design->setModuleTemplatesDir($module->getModuleDirectory($vendor, $moduleName) . 'Backend/design/html');
        $controllerName = $module->getBackendControllersNamespace($vendor, $moduleName) . '\\' . $controllerName;
    } else {
        // если у менеджера вообще никуда нет прав, выведем на этом контроллере ему сообщение
        if (empty($backendControllerName)) {
            $backendControllerName = 'ProductsAdmin';
        }
        $design->setTemplatesDir('backend/design/html');
        $controllerName = '\\Okay\\Admin\\Controllers\\' . $backendControllerName;
    }
}

// CSRF-перевірка виконується до виклику контролера: раніше вона стояла в самому
// кінці файлу, вже після того як контролер відпрацював і мутація сталася.
// AuthAdmin виключено: форма входу рендериться до появи сесії менеджера.
if ($backendControllerName !== 'AuthAdmin' && !$request->checkSession()) {
    $response->setStatusCode(403);
    $response->setContent('Session expired', RESPONSE_TEXT);
    $response->sendContent();
    exit;
}

$backend = new $controllerName($manager, $backendControllerName, $methodName);

$access = call_user_func_array([$backend, 'onInit'], getMethodParams($backend, 'onInit'));
if ($access) {
    if (!method_exists($backend, $methodName)) {
        throw new Exception("Method \"{$methodName}\" is not exists in \"{$controllerName}\" controller");
    }
    call_user_func_array([$backend, $methodName], getMethodParams($backend, $methodName));
}

function getMethodParams($controllerName, $methodName)
{
    global $serviceLocator, $entityFactory;
    $methodParams = [];

    // Проходимся рефлексией по параметрам метода, подеделяем их тип, и пытаемся через DI передать нужный объект
    $reflectionMethod = new \ReflectionMethod($controllerName, $methodName);
    foreach ($reflectionMethod->getParameters() as $parameter) {

        if (($parameterType = $parameter->getType()) !== null) {
            
            $parameterName = $parameterType->getName();
            // Определяем это Entity или сервис из DI
            if (is_subclass_of($parameterName, Entity::class)) {
                $methodParams[] = $entityFactory->get($parameterName);
            } else {
                $methodParams[] = $serviceLocator->getService($parameterName);
            }
        }
    }

    return $methodParams;
}

$response->sendContent();
