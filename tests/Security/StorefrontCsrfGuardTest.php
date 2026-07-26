<?php

namespace Security;

use PHPUnit\Framework\TestCase;

class StorefrontCsrfGuardTest extends TestCase
{
    /**
     * @dataProvider guardedControllerProvider
     */
    public function testMutationControllersInvokeTheGuard($file, $expectedCalls)
    {
        $source = $this->read($file);

        $this->assertSame(
            $expectedCalls,
            substr_count($source, '$this->requireCustomerCsrf('),
            $file
        );
    }

    public function guardedControllerProvider()
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

    /**
     * @dataProvider mutationParamReaderProvider
     */
    public function testMutationParamsAreReadFromPost($file, $forbidden)
    {
        $source = $this->read($file);

        foreach ($forbidden as $needle) {
            $this->assertStringNotContainsString($needle, $source, $file);
        }
    }

    public function mutationParamReaderProvider()
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

    public function testThemeJsSendsTheTokenOnEveryMutation()
    {
        $js = $this->read('design/okay_shop/js/okay.js');

        $this->assertStringContainsString('function okayCsrfToken', $js);

        // Каждый мутирующий вызов должен быть POST и нести токен
        $this->assertSame(8, substr_count($js, 'customer_csrf_token'), 'token missing on some ajax call');
        $this->assertSame(0, substr_count($js, 'okay.router["cart_ajax"],' . "\n" . '    data: {' . "\n" . '      action'), 'cart ajax still sends a bare GET');
    }

    /**
     * @dataProvider tokenCarryingTemplateProvider
     */
    public function testThemeFormsCarryTheToken($template)
    {
        $source = $this->read('design/okay_shop/html/' . $template);

        $this->assertStringContainsString('name="customer_csrf_token"', $source, $template);
    }

    public function tokenCarryingTemplateProvider()
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
