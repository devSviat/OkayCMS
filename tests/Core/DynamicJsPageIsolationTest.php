<?php

namespace Core;

use Okay\Core\Design;
use Okay\Core\TemplateConfig\FrontTemplateConfig;
use Okay\Helpers\MainHelper;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Змінні для scripts.tpl складає сторінка, а забирає їх окремий HTTP-запит. Поки
 * слот сесії був один на всіх, сусідня вкладка чи ajax фільтра затирали його
 * раніше, ніж браузер приходив за скриптом: у кращому разі сторінка діставала
 * чужий блок фільтрів, у гіршому - scripts.tpl падав на порожньому маршруті.
 *
 * Обидва наслідки тихі, тож ловить їх лише перевірка самої розвʼязки: ключ
 * сторінки та адресація знімка за ним.
 */
class DynamicJsPageIsolationTest extends TestCase
{
    protected function setUp(): void
    {
        $_GET = [];
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_SESSION = [];
    }

    public function testTwoCategoriesGetDifferentKeys(): void
    {
        $config = self::templateConfig();

        self::assertNotSame(
            $config->getDynamicJsPageKey('catalog/coffee-machines'),
            $config->getDynamicJsPageKey('catalog/blenders'),
            'дві категорії ділять один ключ - вкладки затруть знімки одна одній'
        );
    }

    /** Мовний префікс - частина адреси, і вкладки різними мовами теж паралельні. */
    public function testLanguagePrefixGivesDifferentKey(): void
    {
        $config = self::templateConfig();

        self::assertNotSame(
            $config->getDynamicJsPageKey('catalog/coffee-machines'),
            $config->getDynamicJsPageKey('ru/catalog/coffee-machines'),
            'дві мови однієї сторінки ділять один ключ'
        );
    }

    public function testQueryStringIsPartOfKey(): void
    {
        $config = self::templateConfig();

        self::assertNotSame(
            $config->getDynamicJsPageKey('all-products'),
            $config->getDynamicJsPageKey('all-products?keyword=кришка'),
            'пошуковий запит не потрапив у ключ'
        );
    }

    public function testSameRequestGivesStableKey(): void
    {
        $config = self::templateConfig();

        self::assertSame(
            $config->getDynamicJsPageKey('catalog/coffee-machines'),
            $config->getDynamicJsPageKey('catalog/coffee-machines'),
            'ключ нестабільний - адреса скрипта не зійдеться зі знімком'
        );
    }

    public function testAssignLandsInThePageSnapshot(): void
    {
        $_SESSION['dynamic_js'] = ['controller' => 'BrandsController'];

        $design = self::design();
        $design->setDynamicJsPageKey('key-a');
        $design->assign('ajax_filter_route', 'brands_features', true);

        self::assertArrayHasKey(Design::DYNAMIC_JS_PAGES, $_SESSION, 'знімок сторінки не створено');
        self::assertSame(
            'brands_features',
            $_SESSION[Design::DYNAMIC_JS_PAGES]['key-a']['vars']['ajax_filter_route']
        );
        self::assertSame(
            'BrandsController',
            $_SESSION[Design::DYNAMIC_JS_PAGES]['key-a']['controller']
        );
    }

    /** Саме цим ajax фільтра й гасив сторінку: чистить спільний слот, знімок не чіпає. */
    public function testSharedSlotResetLeavesTheSnapshotIntact(): void
    {
        $_SESSION['dynamic_js'] = ['controller' => 'BrandsController'];

        $design = self::design();
        $design->setDynamicJsPageKey('key-a');
        $design->assign('ajax_filter_route', 'brands_features', true);

        unset($_SESSION['dynamic_js']);

        self::assertArrayHasKey(Design::DYNAMIC_JS_PAGES, $_SESSION, 'знімок сторінки не створено');
        self::assertSame(
            'brands_features',
            $_SESSION[Design::DYNAMIC_JS_PAGES]['key-a']['vars']['ajax_filter_route']
        );
    }

    /**
     * Ключа немає на скриптових маршрутах і на всьому, що не проходить через
     * activateDynamicJs() - зокрема в адмінці. Інакше присвоєння звідти псує
     * знімок останньої фронтової сторінки.
     */
    public function testAssignWithoutPageKeyWritesNoSnapshot(): void
    {
        $_SESSION['dynamic_js'] = ['controller' => 'ProductsController'];

        self::design()->assign('keyword', null, true);

        self::assertArrayNotHasKey(Design::DYNAMIC_JS_PAGES, $_SESSION);
    }

    /**
     * Спільний слот чиститься на кожному запиті, і знімок мусить так само.
     * Інакше прапорець форми, показаний один раз, лишається в ньому назавжди -
     * відвідувач бачить вікно помилки на кожному наступному заході.
     */
    public function testPageSnapshotIsRebuiltOnEachVisit(): void
    {
        $_SESSION[Design::DYNAMIC_JS_PAGES]['key-a'] = ['vars' => ['call_error' => 'empty phone']];

        $helper = (new ReflectionClass(MainHelper::class))->newInstanceWithoutConstructor();
        (new ReflectionMethod(MainHelper::class, 'resetDynamicJsPage'))->invoke($helper, 'key-a');

        self::assertArrayNotHasKey('key-a', $_SESSION[Design::DYNAMIC_JS_PAGES]);
    }

    /**
     * keyword у знімку не потрібен: footer() пересилає весь $_GET в адресу
     * скрипта, а getKeyword() читає саме звідти. Третій аргумент тут коштує
     * знімка на кожному ajax-запиті - живий пошук витісняв ними справжні.
     */
    public function testKeywordIsNotStoredInSnapshots(): void
    {
        $source = file_get_contents(__DIR__ . '/../../Okay/Helpers/MainHelper.php');

        self::assertSame(
            1,
            preg_match_all("/->assign\('keyword'/", $source),
            'keyword присвоюється двічі - повернувся варіант із dynamicJs'
        );
        self::assertDoesNotMatchRegularExpression(
            "/->assign\('keyword'[^;]*,\s*true\s*\)/",
            $source,
            'keyword знову кладеться у знімок - кожен ajax почне витісняти сторінки'
        );
    }

    /** Знімки лежать у сесії кожного відвідувача, тож їхня кількість має мати стелю. */
    public function testSnapshotsAreCapped(): void
    {
        for ($i = 0; $i < Design::DYNAMIC_JS_PAGES_LIMIT + 4; $i++) {
            $_SESSION[Design::DYNAMIC_JS_PAGES]['key-' . $i] = ['vars' => ['url' => $i]];
        }

        $helper = (new ReflectionClass(MainHelper::class))->newInstanceWithoutConstructor();
        (new ReflectionMethod(MainHelper::class, 'forgetStaleDynamicJsPages'))->invoke($helper);

        self::assertCount(Design::DYNAMIC_JS_PAGES_LIMIT, $_SESSION[Design::DYNAMIC_JS_PAGES]);
        self::assertArrayHasKey('key-8', $_SESSION[Design::DYNAMIC_JS_PAGES], 'відкинуто найсвіжіші замість найстаріших');
        self::assertArrayNotHasKey('key-0', $_SESSION[Design::DYNAMIC_JS_PAGES]);
    }

    private static function templateConfig(): FrontTemplateConfig
    {
        $reflection = new ReflectionClass(FrontTemplateConfig::class);
        $config = $reflection->newInstanceWithoutConstructor();

        // Тема бере участь у ключі лише як шлях до scripts.tpl; сам файл може не існувати.
        $reflection->getProperty('theme')->setValue($config, 'okay_shop');

        return $config;
    }

    private static function design(): Design
    {
        $reflection = new ReflectionClass(Design::class);
        $design = $reflection->newInstanceWithoutConstructor();

        $design->smarty = new class {
            public function assign($var, $value)
            {
                return $this;
            }
        };

        return $design;
    }
}
