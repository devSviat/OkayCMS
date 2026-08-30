<?php


namespace Okay\Core;


use Okay\Core\Modules\Module;
use Okay\Core\Modules\Modules;
use Okay\Core\TemplateConfig\FrontTemplateConfig;
use Okay\Core\TplMod\TplMod;
use Smarty\Smarty;
use Smarty\Filter\Output\TrimWhitespace;
use Detection\MobileDetect;

class Design
{
    
    const TEMPLATES_DEFAULT = 'default';
    const TEMPLATES_MODULE = 'module';

    /** Змінні scripts.tpl, по одному знімку на сторінку, що видала адресу скрипта. */
    const DYNAMIC_JS_PAGES = 'dynamic_js_pages';

    /** Скрипт забирають одразу за сторінкою, тож глибина потрібна лише проти паралельних вкладок. */
    const DYNAMIC_JS_PAGES_LIMIT = 5;

    /**
     * Smarty забороняє статичний доступ до незареєстрованого класу. Пошук іде за
     * літеральним токеном, як його написано в шаблоні, тож "\Okay\Core\Phone" і
     * "Okay\Core\Phone" — різні ключі, і обидві форми треба перелічити. Класи
     * модулів включені, бо їхні шаблони на них посилаються, а власного хука сюди
     * модуль не має; registerClass() кидає виняток на відсутньому класі, тому
     * кожен проходить перевірку перед реєстрацією.
     */
    private const STATIC_CLASSES = [
        \Okay\Core\UserReferer\UserReferer::class,
        \Okay\Core\Phone::class,
        '\\' . \Okay\Core\Phone::class,
        \Okay\Helpers\AiRequests\AiBrandRequest::class,
        \Okay\Helpers\AiRequests\AiCategoryRequest::class,
        \Okay\Helpers\AiRequests\AiProductRequest::class,
        \Okay\Modules\OkayCMS\Banners\DTO\BannerImageSettingsDTO::class,
        \Okay\Modules\OkayCMS\GoogleMerchant\Init\Init::class,
        \Okay\Modules\OkayCMS\Hotline\Init\Init::class,
        \Okay\Modules\OkayCMS\NovaposhtaCost\Init\Init::class,
        \Okay\Modules\OkayCMS\Rozetka\Init\Init::class,
    ];
    
    /**
     * @var Smarty
     */
    public $smarty;

    /** @var MobileDetect */
    public $detect;

    /** @var FrontTemplateConfig */
    private $frontTemplateConfig;

    /** @var Module */
    private $module;

    /** @var Modules */
    private $modules;

    /** @var TplMod */
    private $tplMod;

    /** @var array */
    private $smartyFunctions = [];
    
    /** @var array */
    private $smartyModifiers = [];

    /** @var string */
    private $moduleTemplateDir;

    /** @var string */
    private $defaultTemplateDir;

    private $moduleChangeDir = [];

    private $rootDir;

    /** @var string */
    private $useTemplateDir = self::TEMPLATES_DEFAULT;
    
    private $smartyHtmlMinify;

    /** @var int глибина вкладених fetch() з примусовою мініфікацією */
    private $forceMinifyDepth = 0;

    /**
     * Ключ сторінки, яка зараз малюється, для знімка змінних scripts.tpl.
     * Живе лише в межах запиту: у сесії він застоювався б і на запитах, що
     * не проходять через activateDynamicJs() (адмінка), клав присвоєння в
     * знімок сторонньої сторінки.
     *
     * @var string|null
     */
    private $dynamicJsPageKey;
    

    /**
     * Smarty 5 не викликає нативну PHP-функцію з шаблону, поки її не зареєстровано,
     * і однаково в позиції модифікатора {$x|trim} та виклику {max(1,$n)}. Це білий
     * список того, що дозволено авторам шаблонів; registerSmartyPlugins() реєструє
     * його цілком. Вбудованих модифікаторів Smarty тут немає навмисно: розширення
     * резолвляться раніше за наші реєстрації, тож запис був би мертвим.
     *
     * @var array
     */
    private $allowedPhpFunctions = [
        'str_replace',
        'floor',
        'ceil',
        'max',
        'min',
        'print_r',
        'var_dump',
        'file_exists',
        'stristr',
        'strtotime',
        'urlencode',
        'intval',
        'sizeof',
        'array_intersect',
        'time',
        'base64_encode',
        'preg_replace',
        'preg_match',
        'json_decode',
        'is_file',
        'date',
        'trim',
        'ltrim',
        'rtrim',
        'array_keys',
        'pathinfo',
        'strtolower',
        'strpos',
        'sprintf',
        'vsprintf',
    ];


    public function __construct(
        Smarty $smarty,
        MobileDetect $mobileDetect,
        FrontTemplateConfig $frontTemplateConfig,
        Module $module,
        Modules $modules,
        TplMod $tplMod,
        $smartyCacheLifetime,
        $smartyCompileCheck,
        $smartyHtmlMinify,
        $smartyDebugging,
        $smartySecurity,
        $smartyCaching,
        $smartyForceCompile,
        $rootDir
    ) {
        $this->frontTemplateConfig = $frontTemplateConfig;
        $this->detect         = $mobileDetect;
        $this->module         = $module;
        $this->modules        = $modules;
        $this->tplMod         = $tplMod;
        $this->rootDir        = $rootDir;

        $this->smarty = $smarty;
        $this->smarty->setCompileCheck($smartyCompileCheck ? Smarty::COMPILECHECK_ON : Smarty::COMPILECHECK_OFF);
        $this->smarty->setCaching($smartyCaching ? Smarty::CACHING_LIFETIME_CURRENT : Smarty::CACHING_OFF);
        $this->smarty->setCacheLifetime($smartyCacheLifetime);
        $this->smarty->setDebugging($smartyDebugging);
        $this->smarty->setErrorReporting(E_ALL & ~E_NOTICE & ~E_WARNING);

        $theme = $this->frontTemplateConfig->getTheme();

        if ($smartySecurity == true) {
            $this->smarty->enableSecurity();
            // php_modifiers і php_functions у Smarty 5 більше не існують: єдиний
            // спосіб дозволити нативну функцію - зареєструвати її як модифікатор,
            // що й робить registerSmartyPlugins().
            $this->smarty->security_policy->secure_dir = array(
                $rootDir . 'design/' . $theme,
                $rootDir . 'backend/design',
                $rootDir . 'Okay/Modules',
            );
        }

        $this->defaultTemplateDir = $rootDir.'design/'.$theme.'/html';
        $this->smarty->setCompileDir($rootDir.'compiled/'.$theme);
        $this->smarty->setTemplateDir($this->defaultTemplateDir);

        // Каталог створюється рекурсивно: compiled/ немає на свіжому клоні, і без
        // recursive не створювалось нічого. Повторна перевірка is_dir після невдалого
        // mkdir - гонка двох запитів: обидва бачать !is_dir, обидва створюють, один програє.
        $compileDir = $this->smarty->getCompileDir();
        if (!is_dir($compileDir) && !@mkdir($compileDir, 0777, true) && !is_dir($compileDir)) {
            throw new \RuntimeException(sprintf(
                'Cannot create the Smarty compile directory "%s". Without it no page can be rendered.',
                $compileDir
            ));
        }
        
        $this->smarty->setCacheDir('cache');
        
        $this->smartyHtmlMinify = $smartyHtmlMinify;
        // Smarty 5 позначив loadFilter()/unloadFilter() застарілими, а вони ще й
        // знімали мініфікацію із зовнішнього fetch(), коли завершувався вкладений.
        // Один фільтр на весь час життя, рішення - у момент виклику.
        $this->smarty->registerFilter('output', [$this, 'minifyOutput']);

        if ($smartyForceCompile) {
            $smarty->setForceCompile(true);
        }
        
        $this->smarty->registerFilter('pre', [$this, 'applyTplModifiers']);
    }
    
    /** @param \Smarty\Template $s */
    public function applyTplModifiers($content, $s)
    {
        // Smarty 5 прибрав _current_file. getFilepath() дає абсолютний шлях, або
        // null/false для нефайлових ресурсів (string:, eval:) - обидва стають ''.
        $source = $s->getSource();
        $currentFile = $source !== null ? (string)$source->getFilepath() : '';

        // Определяем модификации чего сейчас нам нужны, фронта или бека
        if (strpos($currentFile, $this->rootDir.'backend'.DIRECTORY_SEPARATOR.'design'.DIRECTORY_SEPARATOR.'html') !== false) {
            $modifications = $this->modules->getBackendModulesTplModifications();
        } else {
            $modifications = $this->modules->getFrontModulesTplModifications();
        }
        $fileModifications = [];
        if (!empty($modifications)) {
            foreach ($modifications as $modificationDTO) {
                if (DIRECTORY_SEPARATOR.ltrim($modificationDTO->getFile(), DIRECTORY_SEPARATOR) == substr($currentFile, -strlen(DIRECTORY_SEPARATOR.$modificationDTO->getFile()))) {
                    $fileModifications = array_merge($fileModifications, $modificationDTO->getChanges());
                }
            }
        }
        
        if (!empty($fileModifications)) {
            $content = $this->tplMod->buildFile($content, $fileModifications);
        }
        
        return $content;
    }

    /**
     * Метод нужен для модулей, если в каком-то экстендере или еще где нужно обработать tpl файл
     * нужно предварительно вызвать этот метод, чтобы переключить директорию tpl файлов.
     * После вызова fetch() нужно обязательно вернуть стандартную директорию методом rollbackTemplatesDir()
     * 
     * @param $moduleClassName
     * @throws \Exception
     */
    public function setModuleDir($moduleClassName)
    {
        
        $vendor = $this->module->getVendorName($moduleClassName);
        $name = $this->module->getModuleName($moduleClassName);

        $moduleTemplateDir = $this->module->generateModuleTemplateDir(
            $vendor,
            $name
        );

        $this->moduleChangeDir[] = [
            'prev_module_dir' => $this->getModuleTemplatesDir(),
            'is_use_prev_module_dir' => $this->isUseModuleDir(),
        ];
        
        $this->setModuleTemplatesDir($moduleTemplateDir);
        $this->useModuleDir();
    }

    /**
     * Метод возвращает стандартную директорию tpl файлов.
     * Применяется если в модуле сменили директорию tpl файлов посредством метода setModuleDir()
     */
    public function rollbackTemplatesDir()
    {
        
        if ($moduleChangeDir = array_pop($this->moduleChangeDir)) {
            if (!empty($moduleChangeDir['prev_module_dir'])) {
                $this->setModuleTemplatesDir($moduleChangeDir['prev_module_dir']);
            }
            if (!$moduleChangeDir['is_use_prev_module_dir']) {
                $this->useDefaultDir();
            }
        } else {
            $this->useDefaultDir();
        }
    }
    
    /**
     * Проверка существует ли данный файл шаблона
     * 
     * @param $tplFile
     * @return bool
     * @throws \Smarty\Exception
     */
    public function templateExists($tplFile)
    {
        $tplFile = mb_strcut($tplFile, 0, 250);

        $this->setSmartyTemplatesDir();

        return $this->smarty->templateExists(trim(preg_replace('~[\n\r]*~', '', $tplFile)));
    }
    
    public function registerPlugin($type, $tag, $callback)
    {
        switch ($type) {
            case 'modifier':
                $this->smartyModifiers[$tag] = $callback;
                break;
            case 'function':
                $this->smartyFunctions[$tag] = $callback;
                break;
        }
    }

    /**
     * @param string $var
     * @param mixed $value
     * @param bool $dynamicJs Если установить в true, переменная будет доступна в файле scripts.tpl клиентского шаблона,
     * как обычная Smarty переменная
     * @return \Smarty\Data
     */
    public function assign($var, $value, $dynamicJs = false)
    {

        if ($dynamicJs === true) {
            $_SESSION['dynamic_js']['vars'][$var] = $value;

            // Спільний слот переживає лише одну сторінку в польоті, тож дублюємо
            // змінну під ключем тієї сторінки, яка й видасть адресу скрипта.
            if ($this->dynamicJsPageKey !== null) {
                $_SESSION[self::DYNAMIC_JS_PAGES][$this->dynamicJsPageKey]['controller'] = $_SESSION['dynamic_js']['controller'] ?? null;
                $_SESSION[self::DYNAMIC_JS_PAGES][$this->dynamicJsPageKey]['vars'][$var] = $value;
            }
        }

        return $this->smarty->assign($var, $value);
    }

    /**
     * @param string|null $pageKey
     * @return void
     */
    public function setDynamicJsPageKey($pageKey)
    {
        $this->dynamicJsPageKey = $pageKey;
    }

    /**
     * @return string|null
     */
    public function getDynamicJsPageKey()
    {
        return $this->dynamicJsPageKey;
    }

    /**
     * @param $var
     * @param $value
     * 
     * Метод позволяет передать переменную с PHP непосредственно в JS код
     * Считать переменную можно будет как okay.var_name
     */
    public function assignJsVar($var, $value)
    {
        $_SESSION['common_js']['vars'][$var] = $value;
    }

    /*Отображение конкретного шаблона*/
    public function fetch($template, $forceMinify = false)
    {
        if ($forceMinify === true) {
            $this->forceMinifyDepth++;
        }

        $this->registerSmartyPlugins();

        $this->setSmartyTemplatesDir();

        try {
            return $this->smarty->fetch($template);
        } finally {
            if ($forceMinify === true) {
                $this->forceMinifyDepth--;
            }
        }
    }

    public function minifyOutput($output, $template)
    {
        if (!$this->smartyHtmlMinify && $this->forceMinifyDepth === 0) {
            return $output;
        }

        return (new TrimWhitespace())->filter($output, $template);
    }

    public function useDefaultDir()
    {
        $this->useTemplateDir = self::TEMPLATES_DEFAULT;
        $this->setSmartyTemplatesDir();
    }

    public function useModuleDir()
    {
        $this->useTemplateDir = self::TEMPLATES_MODULE;
        $this->setSmartyTemplatesDir();
    }

    public function isUseModuleDir()
    {
        if ($this->useTemplateDir === self::TEMPLATES_MODULE) {
            return true;
        }
        return false;
    }
    
    private function registerSmartyPlugins()
    {
        // registerPlugin() у Smarty 5 кидає виняток на вже зайнятому тезі, тож
        // кожна реєстрація нижче йде під перевіркою.
        foreach ($this->smartyModifiers as $tag => $callback) {
            if (!isset($this->smarty->registered_plugins['modifier'][$tag])) {
                $this->smarty->registerPlugin('modifier', $tag, $callback);
            }
            unset($this->smartyModifiers[$tag]);
        }

        foreach ($this->smartyFunctions as $tag => $callback) {
            if (!isset($this->smarty->registered_plugins['function'][$tag])) {
                $this->smarty->registerPlugin('function', $tag, $callback);
            }
            unset($this->smartyFunctions[$tag]);
        }

        // Smarty 5 не викликає нативну PHP-функцію з шаблону, поки її не
        // зареєстровано - однаково в позиції модифікатора {$x|trim} і в позиції
        // виклику {max(1,$n)}. Реєстрація тут єдиний механізм, і вона не залежить
        // від політики безпеки: із smarty_security = false шаблони мають так само
        // працювати. Наші плагіни зареєстровані вище, тому свої теги вони й тримають.
        foreach ($this->allowedPhpFunctions as $phpFunction) {
            if (function_exists($phpFunction)
                && !isset($this->smarty->registered_plugins['modifier'][$phpFunction])) {
                $this->smarty->registerPlugin('modifier', $phpFunction, $phpFunction);
            }
        }

        foreach (self::STATIC_CLASSES as $staticClass) {
            $className = ltrim($staticClass, '\\');
            if (!isset($this->smarty->registered_classes[$staticClass]) && class_exists($className)) {
                $this->smarty->registerClass($staticClass, $className);
            }
        }
    }

    public function getDefaultTemplatesDir()
    {
        return rtrim($this->defaultTemplateDir , '/');
    }

    public function setModuleTemplatesDir($moduleTemplateDir)
    {
        $this->moduleTemplateDir = $moduleTemplateDir;
        $this->setSmartyTemplatesDir();
    }

    public function getModuleTemplatesDir()
    {
        return rtrim((string)$this->moduleTemplateDir , '/');
    }

    /*Установка директории файлов шаблона(отображения)*/
    public function setTemplatesDir($dir)
    {
        $dir = rtrim($dir, '/') . '/';
        if (!is_string($dir)) {
            throw new \Exception("Param \$dir must be string");
        }
        
        $this->defaultTemplateDir = $dir;
        $this->smarty->setTemplateDir($dir);
    }

    /*Установка директории для готовых файлов для отображения*/
    public function setCompiledDir($dir)
    {
        $this->smarty->setCompileDir($dir);
    }

    /*Получение директории файлов шаблона(отображения)*/
    public function getTemplatesDir()
    {
        $dirs = $this->smarty->getTemplateDir();
        return reset($dirs);
    }

    /*Получение директории для готовых файлов для отображения*/
    public function getCompiledDir()
    {
        return $this->smarty->getCompileDir();
    }

    /*Выборка переменой*/
    public function getVar($name)
    {
        return $this->smarty->getTemplateVars($name);
    }
    
    public function get_var($name)
    {
        trigger_error('Method ' . __METHOD__ . ' is deprecated. Please use getVar', E_USER_DEPRECATED);
        return $this->getVar($name);
    }

    /*Очитска кэша Smarty*/
    public function clearCache()
    {
        $this->smarty->clearAllCache();
    }

    /*Определение мобильного устройства*/
    public function isMobile()
    {
        return $this->detect->isMobile();
    }

    /*Определение планшетного устройства*/
    public function isTablet()
    {
        return $this->detect->isTablet();
    }

    public function setSmartyTemplatesDir()
    {
        if ($this->isUseModuleDir() === false) {
            $this->smarty->setTemplateDir($this->getDefaultTemplatesDir());
        } else {
            $namespace = str_replace($this->rootDir, '', $this->getModuleTemplatesDir());
            $namespace = str_replace('/', '\\', $namespace);

            $vendor = $this->module->getVendorName($namespace);
            $moduleName = $this->module->getModuleName($namespace);
            /**
             * Устанавливаем директории поиска файлов шаблона как:
             * Директория модуля в дизайне (если модуль кастомизируют)
             * Директория модуля
             * Стандартная директория дизайна
             */
            $this->smarty->setTemplateDir([
                dirname($this->getDefaultTemplatesDir()) . "/modules/{$vendor}/{$moduleName}/html",
                $this->getModuleTemplatesDir(),
                $this->getDefaultTemplatesDir(),
            ]);
        }
    }
    
    public function clearCompiled()
    {
        $theme = $this->frontTemplateConfig->getTheme();
        $dir = $this->rootDir.'compiled/'.$theme;
        if ($handle = opendir($dir)) {
            while (false !== ($file = readdir($handle))) {
                if ($file != "." && $file != "..") {
                    @unlink($dir."/".$file);
                }
            }
            closedir($handle);
        }

        $dir = $this->rootDir.'backend/design/compiled/';
        if ($handle = opendir($dir)) {
            while (false !== ($file = readdir($handle))) {
                if ($file != "." && $file != ".." && $file != '.keep_folder') {
                    @unlink($dir."/".$file);
                }
            }
            closedir($handle);
        }
    }

    private function getModuleVendorByPath($path)
    {
        $path = str_replace(DIRECTORY_SEPARATOR, '/', $path);
        return preg_replace('~.*/?Okay/Modules/([a-zA-Z0-9]+)/([a-zA-Z0-9]+)/?.*~', '$1', $path);
    }

    private function getModuleNameByPath($path)
    {
        $path = str_replace(DIRECTORY_SEPARATOR, '/', $path);
        return preg_replace('~.*/?Okay/Modules/([a-zA-Z0-9]+)/([a-zA-Z0-9]+)/?.*~', '$2', $path);
    }

}
