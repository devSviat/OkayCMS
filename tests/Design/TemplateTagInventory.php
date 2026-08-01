<?php


namespace Design;


use Okay\Core\Design;
use Okay\Core\SmartyPlugins\Func;
use Okay\Core\SmartyPlugins\Modifier;

/**
 * Набір тегів, які двигун реєструє у Smarty, зібраний рефлексією з тих самих
 * джерел, що читає рантайм: класів плагінів і Design::$allowedPhpFunctions.
 *
 * Саме тому це не копія списку: якщо хтось перейменує тег або прибере функцію зі
 * списку, інвентар зміниться разом із кодом і залежні тести почервоніють.
 */
final class TemplateTagInventory
{
    /** @var array{function: string[], modifier: string[]}|null */
    private static $pluginTags;

    /**
     * Дослівна транскрипція Plugin::register(): тип виводиться з базового класу,
     * тег — з властивості $tag або з імені класу в нижньому регістрі.
     *
     * Результат кешується: інвентар незмінний у межах прогону, а тести з
     * @dataProvider питають його на кожен шаблон.
     *
     * @return array{function: string[], modifier: string[]}
     */
    public static function pluginTags(): array
    {
        if (self::$pluginTags !== null) {
            return self::$pluginTags;
        }

        $tags = ['function' => [], 'modifier' => []];

        foreach (self::pluginClasses() as $class) {
            $reflector = new \ReflectionClass($class);

            if ($reflector->isSubclassOf(Func::class)) {
                $type = 'function';
            } elseif ($reflector->isSubclassOf(Modifier::class)) {
                $type = 'modifier';
            } else {
                continue;
            }

            $declaredTag = $reflector->getDefaultProperties()['tag'] ?? null;
            $tags[$type][] = !empty($declaredTag)
                ? $declaredTag
                : strtolower($reflector->getShortName());
        }

        sort($tags['function']);
        sort($tags['modifier']);

        return self::$pluginTags = $tags;
    }

    /**
     * @return string[] FQCN плагінів ядра й усіх модулів, незалежно від того, чи
     *                  модуль встановлений: кожен наявний .tpl має компілюватись.
     */
    public static function pluginClasses(): array
    {
        $classes = [];

        foreach (glob(self::rootDir() . 'Okay/Core/SmartyPlugins/Plugins/*.php') as $file) {
            $classes[] = 'Okay\\Core\\SmartyPlugins\\Plugins\\' . basename($file, '.php');
        }

        // Init/SmartyPlugins.php модулів — це чисті `return [...]` без побічних
        // ефектів, тож include безпечний, а класи не інстанціюються взагалі.
        foreach (glob(self::rootDir() . 'Okay/Modules/*/*/Init/SmartyPlugins.php') as $file) {
            $definitions = include $file;
            if (is_array($definitions)) {
                $classes = array_merge($classes, array_column($definitions, 'class'));
            }
        }

        return array_values(array_unique(array_filter($classes, 'class_exists')));
    }

    /**
     * @return string[] Нативні PHP-функції, які двигун дозволяє в шаблонах,
     *                  списком як є: 'empty' та 'isset' — мовні конструкції, їх
     *                  не видно через function_exists, але політиці безпеки вони
     *                  потрібні. Рантайм так само віддає список нефільтрованим.
     */
    public static function phpFunctionModifiers(): array
    {
        $properties = (new \ReflectionClass(Design::class))->getDefaultProperties();

        if (!isset($properties['allowedPhpFunctions'])) {
            throw new \RuntimeException(
                'Design::$allowedPhpFunctions не знайдено. Інвентар мовчки став би порожнім, '
                . 'а шаблони посипались би з незрозумілим "unknown modifier".'
            );
        }

        return $properties['allowedPhpFunctions'];
    }

    public static function isSmarty5(): bool
    {
        return class_exists('Smarty\\Smarty');
    }

    public static function smartyClass(): string
    {
        return self::isSmarty5() ? 'Smarty\\Smarty' : 'Smarty';
    }

    /**
     * Smarty з тим самим набором тегів, що й рантайм, але з заглушками замість
     * колбеків: компіляція їх не викликає, а нам потрібні лише імена.
     *
     * @param string[] $templateDirs
     */
    public static function createSmarty(array $templateDirs, string $compileDir)
    {
        self::loadApplicationConstants();

        $smartyClass = self::smartyClass();
        $smarty = new $smartyClass();

        $smarty->setTemplateDir(array_values(array_filter($templateDirs, 'is_dir')));
        $smarty->setCompileDir($compileDir);
        // Без цього застарілий compiled мовчки зафарбував би тест у зелене.
        $smarty->setForceCompile(true);

        $phpFunctions = self::phpFunctionModifiers();

        if (!self::isSmarty5()) {
            // У v4 нативну функцію пускає політика безпеки, а не реєстрація, тож
            // без неї той самий список не був би тут білим списком і тест утратив
            // би сенс. У v5 ці властивості прибрані, і білим списком є реєстрація.
            $smarty->enableSecurity();
            $smarty->security_policy->secure_dir = [
                self::rootDir() . 'design',
                self::rootDir() . 'backend/design',
                self::rootDir() . 'Okay/Modules',
                self::rootDir() . 'Okay/xml',
            ];
            $smarty->security_policy->php_modifiers = $phpFunctions;
            $smarty->security_policy->php_functions = $phpFunctions;
        }

        foreach (self::staticClasses() as $staticClass) {
            $className = ltrim($staticClass, '\\');
            if (!isset($smarty->registered_classes[$staticClass]) && class_exists($className)) {
                $smarty->registerClass($staticClass, $className);
            }
        }

        // Порядок як у Design::registerSmartyPlugins(): спершу наші плагіни, і
        // лише потім нативні функції з пропуском уже зайнятих тегів. Саме він дає
        // нашим `date`, `time`, `first` виграти імена в однойменних функцій PHP.
        $tags = self::pluginTags();
        foreach ($tags['modifier'] as $tag) {
            self::registerStub($smarty, 'modifier', $tag);
        }
        foreach ($tags['function'] as $tag) {
            self::registerStub($smarty, 'function', $tag);
        }

        // Мовні конструкції ('empty', 'isset') реєстрації не потребують і не піддаються.
        foreach (array_filter($phpFunctions, 'function_exists') as $function) {
            if (!self::isRegistered($smarty, 'modifier', $function)) {
                $smarty->registerPlugin('modifier', $function, $function);
            }
        }

        return $smarty;
    }

    private static function isRegistered($smarty, string $type, string $tag): bool
    {
        return self::isSmarty5()
            ? $smarty->getRegisteredPlugin($type, $tag) !== null
            : isset($smarty->registered_plugins[$type][$tag]);
    }

    private static function registerStub($smarty, string $type, string $tag): void
    {
        if (self::isRegistered($smarty, $type, $tag)) {
            return;
        }

        $smarty->registerPlugin($type, $tag, $type === 'modifier'
            ? static function (...$arguments) {
                return '';
            }
            : static function ($params, $template = null) {
                return '';
            });
    }

    /**
     * Шаблони звертаються до констант застосунку просто як {CANONICAL_FIRST_PAGE}.
     * Smarty розбирає це лише коли константа визначена, а bootstrap тестів - це
     * самий лише vendor/autoload.php.
     */
    private static function loadApplicationConstants(): void
    {
        require_once self::rootDir() . 'Okay/Core/config/constants.php';
    }

    /**
     * Константа приватна навмисно: рефлексія читає її й так, а публічною вона
     * розширювала б API класу лише заради тесту.
     *
     * @return string[]
     */
    private static function staticClasses(): array
    {
        return (new \ReflectionClass(Design::class))->getConstant('STATIC_CLASSES');
    }

    public static function rootDir(): string
    {
        return dirname(__DIR__, 2) . '/';
    }
}
