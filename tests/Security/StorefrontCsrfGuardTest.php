<?php

namespace Security;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class StorefrontCsrfGuardTest extends TestCase
{
    #[DataProvider('guardedControllerProvider')]
    public function testMutationControllersInvokeTheGuard($file, $expectedCalls)
    {
        $source = $this->read($file);

        $this->assertSame(
            $expectedCalls,
            substr_count($source, '$this->requireCustomerCsrf('),
            $file
        );
    }

    public static function guardedControllerProvider()
    {
        return [
            'cart'       => ['Okay/Controllers/CartController.php', 4],
            'wishlist'   => ['Okay/Controllers/WishListController.php', 1],
            'comparison' => ['Okay/Controllers/ComparisonController.php', 1],
            'subscribe'  => ['Okay/Controllers/SubscribeController.php', 1],
            'feedback'   => ['Okay/Controllers/FeedbackController.php', 1],
        ];
    }

    /**
     * Лічильник вище рахує виклики в цілому файлі, і саме цього виявилось замало:
     * CartController містив три виклики й проходив, поки render() додавав товар
     * у кошик із $_GET взагалі без охорони. Тобто GET /cart?variant=17 наповнював
     * кошик відвідувача з чужої сторінки.
     *
     * Тому перевірка тут інша: у ТІЛІ конкретного методу виклик охорони має йти
     * РАНІШЕ за мутацію. Це перевірка порядку в коді, не в рантаймі -
     * requireCustomerCsrf() завершується через exit, тож викликати його в тесті
     * не можна. Але саме порядку бракувало.
     */
    #[DataProvider('guardOrderProvider')]
    public function testGuardRunsBeforeTheMutation($class, $method, $mutation)
    {
        $body = $this->methodBody($class, $method);

        $guardAt = strpos($body, '$this->requireCustomerCsrf(');
        $this->assertNotFalse($guardAt, "$class::$method() мутує без виклику охорони");

        $mutationAt = strpos($body, $mutation);
        $this->assertNotFalse($mutationAt, "у $class::$method() не знайдено $mutation - тест застарів");

        $this->assertLessThan(
            $mutationAt,
            $guardAt,
            "$class::$method() виконує $mutation до перевірки токена"
        );
    }

    public static function guardOrderProvider()
    {
        $cart = \Okay\Controllers\CartController::class;

        return [
            // render() має ТРИ мутуючі гілки: додавання варіанта, оновлення
            // кількостей і оформлення. Перевіряються всі три, бо strpos бачить
            // лише перше входження, і один запис засвідчив би менше, ніж
            // здається.
            'cart page: add without JS'  => [$cart, 'render', '$cart->addItem('],
            'cart page: update amounts'  => [$cart, 'render', '$cart->updateItem('],
            'cart page: checkout'        => [$cart, 'render', '$ordersHelper->add('],
            'cart ajax'                 => [$cart, 'cartAjax', '$cart->updateItem('],
            'cart remove'               => [$cart, 'removeItem', '$cart->deleteItem('],
            'cart add'                  => [$cart, 'addItem', '$cart->addItem('],
            'wishlist' => [
                \Okay\Controllers\WishListController::class, 'ajaxUpdate', '$wishList->addItem(',
            ],
            'comparison' => [
                \Okay\Controllers\ComparisonController::class, 'ajaxUpdate', '$comparison->addItem(',
            ],
        ];
    }

    /**
     * Тіло методу за межами, які дає рефлексія: сигнатури тут багатослівні
     * (DI через типи аргументів), тож регулярка по файлу різала б не там.
     */
    private function methodBody($class, $method)
    {
        $reflection = new \ReflectionMethod($class, $method);
        $file = file($reflection->getFileName());
        $start = $reflection->getStartLine() - 1;
        $length = $reflection->getEndLine() - $start;

        return implode('', array_slice($file, $start, $length));
    }

    public function testAbstractControllerExposesTheGuardAndTheToken()
    {
        $source = $this->read('Okay/Controllers/AbstractController.php');

        $this->assertStringContainsString('function requireCustomerCsrf', $source);
        $this->assertStringContainsString('function customerCsrfToken', $source);
        $this->assertStringContainsString("assign('customer_csrf_token'", $source);
    }

    /**
     * Тіло охорони живе в сервісі, бо POST обробляють і хелпери, де контролера
     * немає. Коди відповіді перевіряються там, де вони тепер стоять.
     */
    public function testGuardServiceRefusesWrongMethodAndWrongToken()
    {
        $source = $this->read('Okay/Core/Security/StorefrontGuard.php');

        $this->assertStringContainsString("method('post')", $source);
        $this->assertStringContainsString('CustomerCsrfToken::check', $source);
        $this->assertStringContainsString('reject(405', $source);
        $this->assertStringContainsString('reject(403', $source);
    }

    #[DataProvider('mutationParamReaderProvider')]
    public function testMutationParamsAreReadFromPost($file, $forbidden)
    {
        $source = $this->read($file);

        foreach ($forbidden as $needle) {
            $this->assertStringNotContainsString($needle, $source, $file);
        }
    }

    public static function mutationParamReaderProvider()
    {
        return [
            'cart' => [
                'Okay/Controllers/CartController.php',
                [
                    "\$request->get('action')",
                    "\$request->get('variant_id', 'integer')",
                    // Додавання в кошик зі сторінки /cart: читалось із $_GET,
                    // тож будь-який <img src> наповнював чужий кошик.
                    "\$request->get('variant', 'integer')",
                ],
            ],
            'wishlist' => [
                'Okay/Controllers/WishListController.php',
                ["\$this->request->get('id', 'integer')", "\$this->request->get('action')"],
            ],
            'comparison' => [
                'Okay/Controllers/ComparisonController.php',
                ["\$this->request->get('product', 'integer')", "\$this->request->get('action')"],
            ],
        ];
    }

    public static function themeProvider()
    {
        return [['okay_shop'], ['vibe_shop']];
    }

    #[DataProvider('themeProvider')]
    public function testThemeJsSendsTheTokenOnEveryMutation($theme)
    {
        $js = $this->read('design/' . $theme . '/js/okay.js');

        $this->assertStringContainsString('function okayCsrfToken', $js, $theme);

        // Кожен мутуючий виклик має бути POST і нести токен
        $this->assertSame(6, substr_count($js, 'customer_csrf_token: okayCsrfToken()'), $theme);
        $this->assertSame(2, substr_count($js, '&customer_csrf_token='), $theme);
        $this->assertSame(0, substr_count($js, 'okay.router["cart_ajax"],' . "\n" . '    data: {' . "\n" . '      action'), 'cart ajax still sends a bare GET');
    }

    /**
     * Стоковий OkayCMS читає ці ендпоінти з $_GET, тож параметри мають дублюватись
     * у рядок запиту - інакше тема мовчки не працює на нефоркнутому рушії.
     *
     */
    #[DataProvider('themeProvider')]
    public function testMutatingCallsGoThroughOkayAjax($theme)
    {
        $js = $this->read('design/' . $theme . '/js/okay.js');

        $this->assertStringContainsString('function okayAjax', $js, $theme);
        $this->assertSame(6, substr_count($js, 'okayAjax({'), $theme);
        $this->assertSame(
            0,
            preg_match_all('~\$\.ajax\(\{\s*url: okay\.router\["(?:cart_ajax|wishlist_ajax|comparison_ajax)"\]~', $js),
            $theme
        );
        // Токен лишається в тілі: в URL він осів би в логах і Referer
        $this->assertStringContainsString('key !== "customer_csrf_token"', $js, $theme);
    }

    #[DataProvider('tokenCarryingTemplateProvider')]
    public function testThemeFormsCarryTheToken($theme, $template)
    {
        $source = $this->read('design/' . $theme . '/html/' . $template);

        $this->assertStringContainsString(
            'name="customer_csrf_token"',
            $source,
            "$theme/$template мутує без токена"
        );
    }

    public static function tokenCarryingTemplateProvider()
    {
        $cases = [];
        $templates = [
            'feedback.tpl',
            'product.tpl',
            'product_list.tpl',
            'cart.tpl',
            // Постить на ту сторінку, де стоїть, зокрема на /cart, де охорона
            // вимагає токен. Без нього форма зберігала заявку і аж тоді
            // отримувала 403.
            'callback.tpl',
        ];

        foreach (['okay_shop', 'vibe_shop'] as $theme) {
            foreach ($templates as $template) {
                $cases["$theme/$template"] = [$theme, $template];
            }
        }

        return $cases;
    }

    /**
     * Вкладена <form> у розмітці кошика непомітно ламає оформлення: браузер
     * викидає вкладений відкривальний тег, а вкладений </form> закриває
     * ЗОВНІШНЮ форму, і кнопка «Оформити» разом з усіма полями під нею лишається
     * без форми. Сторінка при цьому виглядає цілою, тести зелені, а покупець без
     * JS просто не може оформити замовлення.
     */
    #[DataProvider('themeProvider')]
    public function testCartRowsDoNotNestAForm($theme)
    {
        $source = $this->read('design/' . $theme . '/html/cart_purchases.tpl');

        // Коментарі {* *} відкидаються: саме в них пояснено, чому форми тут
        // немає, і без цього тест ловив би власне пояснення.
        $markup = preg_replace('~\{\*.*?\*\}~s', '', $source);

        $this->assertSame(
            0,
            substr_count($markup, '<form'),
            "$theme/cart_purchases.tpl рендериться всередині форми оформлення, " .
            "тож власної форми мати не може - видалення робиться через formaction"
        );
    }

    /**
     * Форма fn_variants - це шлях додавання в кошик без JS. Якщо з неї зникне
     * method="post", вона мовчки повернеться до GET: контролер її проігнорує,
     * покупець отримає незмінений кошик без жодної помилки, а тест на токен
     * лишиться зеленим - інпут же на місці.
     */
    #[DataProvider('buyFormProvider')]
    public function testBuyFormsPostRatherThanGet($theme, $template)
    {
        $source = $this->read('design/' . $theme . '/html/' . $template);

        preg_match_all('~<form[^>]*\bfn_variants\b[^>]*>~', $source, $matches);

        $this->assertNotEmpty($matches[0], "$theme/$template: форму fn_variants не знайдено");

        foreach ($matches[0] as $form) {
            $this->assertStringContainsString('method="post"', $form, "$theme/$template: $form");
        }
    }

    public static function buyFormProvider()
    {
        $cases = [];
        foreach (['okay_shop', 'vibe_shop'] as $theme) {
            foreach (['product.tpl', 'product_list.tpl'] as $template) {
                $cases["$theme/$template"] = [$theme, $template];
            }
        }

        return $cases;
    }

    private function read($file)
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $file);
        $this->assertIsString($source, $file);

        return $source;
    }
}
