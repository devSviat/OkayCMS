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
            'cart'       => ['Okay/Controllers/CartController.php', 3],
            'wishlist'   => ['Okay/Controllers/WishListController.php', 1],
            'comparison' => ['Okay/Controllers/ComparisonController.php', 1],
            'subscribe'  => ['Okay/Controllers/SubscribeController.php', 1],
            'feedback'   => ['Okay/Controllers/FeedbackController.php', 1],
        ];
    }

    public function testAbstractControllerExposesTheGuardAndTheToken()
    {
        $source = $this->read('Okay/Controllers/AbstractController.php');

        $this->assertStringContainsString('function requireCustomerCsrf', $source);
        $this->assertStringContainsString('function customerCsrfToken', $source);
        $this->assertStringContainsString("assign('customer_csrf_token'", $source);
        $this->assertStringContainsString('setStatusCode(405)', $source);
        $this->assertStringContainsString('setStatusCode(403)', $source);
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
                ["\$request->get('action')", "\$request->get('variant_id', 'integer')"],
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
    public function testThemeFormsCarryTheToken($template)
    {
        $source = $this->read('design/okay_shop/html/' . $template);

        $this->assertStringContainsString('name="customer_csrf_token"', $source, $template);
    }

    public static function tokenCarryingTemplateProvider()
    {
        return [
            'feedback' => ['feedback.tpl'],
        ];
    }

    private function read($file)
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $file);
        $this->assertIsString($source, $file);

        return $source;
    }
}
