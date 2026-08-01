<?php


namespace Core\SmartyPlugins;


use PHPUnit\Framework\TestCase;

/**
 * Клас плагіна в Plugins/ і його запис у масиві DI - дві різні зміни, і забути
 * другу нічого не заважає. Тег тоді не реєструється, і сторінка падає на
 * "unknown modifier" уже в проді: клас існує, тож compile-гейт бачить тег і
 * підставляє заглушку, а всі інші тести плагінів взагалі не торкаються.
 */
class PluginRegistrationTest extends TestCase
{
    public function testEveryCorePluginIsRegisteredInTheContainer(): void
    {
        $root = self::rootDir();

        $classes = [];
        foreach (glob($root . 'Okay/Core/SmartyPlugins/Plugins/*.php') as $file) {
            $classes[] = basename($file, '.php');
        }
        sort($classes);

        // Файл підключати не можна: він тягне контейнер і одразу реєструє плагіни.
        $source = file_get_contents($root . 'Okay/Core/SmartyPlugins/SmartyPlugins.php');
        preg_match_all('~Plugins\\\\(\w+)::class\s*=>~', $source, $matches);
        $registered = array_values(array_unique($matches[1]));
        sort($registered);

        $this->assertNotEmpty($registered, 'жодного плагіна не розпізнано - тест утратив би сенс');
        $this->assertSame(
            [],
            array_values(array_diff($classes, $registered)),
            'плагіни лежать у Plugins/, але не зареєстровані в SmartyPlugins.php'
        );
        $this->assertSame(
            [],
            array_values(array_diff($registered, $classes)),
            'у SmartyPlugins.php зареєстровані класи, яких у Plugins/ немає'
        );
    }

    /**
     * @dataProvider moduleProvider
     */
    public function testEveryModulePluginIsRegisteredInItsInit(string $moduleDir): void
    {
        $root = self::rootDir();

        $classes = [];
        foreach (glob($root . $moduleDir . '/Plugins/*.php') as $file) {
            $classes[] = basename($file, '.php');
        }
        sort($classes);

        $init = $root . $moduleDir . '/Init/SmartyPlugins.php';
        $this->assertFileExists($init, "{$moduleDir}: є Plugins/, але немає Init/SmartyPlugins.php");

        // На відміну від ядра, це чистий `return [...]` без побічних ефектів.
        $definitions = include $init;
        $registered = [];
        foreach (array_column((array)$definitions, 'class') as $fqcn) {
            $registered[] = substr((string)strrchr($fqcn, '\\'), 1);
        }
        sort($registered);

        $this->assertSame(
            [],
            array_values(array_diff($classes, $registered)),
            "{$moduleDir}: плагіни лежать у Plugins/, але не зареєстровані в Init/SmartyPlugins.php"
        );
    }

    public function moduleProvider(): array
    {
        $root = self::rootDir();
        $cases = [];

        foreach (glob($root . 'Okay/Modules/*/*/Plugins', GLOB_ONLYDIR) as $pluginsDir) {
            $moduleDir = substr(dirname($pluginsDir), strlen($root));
            $cases[$moduleDir] = [$moduleDir];
        }

        return $cases;
    }

    private static function rootDir(): string
    {
        return dirname(__DIR__, 3) . '/';
    }
}
