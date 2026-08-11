<?php

namespace Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Заміри на стенді до правки: другий POST кожної з цих форм створював другий
 * рядок і другий лист - і послідовний (F5), і паралельний (подвійний клік).
 * Редирект у коментарях рятував лише браузерний F5, від повтору запиту - ні.
 *
 * Перевірка тут статична: обробники завершуються редиректом або exit,
 * тож викликати їх у юніт-тесті не можна. Але саме порядку й наявності
 * бракувало.
 */
class FormResubmitGuardTest extends TestCase
{
    #[DataProvider('guardedWriterProvider')]
    public function testWriteIsGuardedByAOneTimeToken($file, $form, $accept, $write)
    {
        $source = $this->read($file);

        // Ім'я форми оголошене константою, тому звіряємо саме її значення:
        // воно має збігтися з тим, що друкує {form_token name="…"} у шаблоні.
        $this->assertMatchesRegularExpression(
            "/const [A-Z_]+ = '{$form}';/",
            $source,
            "$file не оголошує імені форми {$form}"
        );
        $this->assertStringContainsString('FormToken::accept(', $source, "$file не перевіряє повтор");

        $acceptAt = strpos($source, $accept);
        $writeAt  = strpos($source, $write);

        $this->assertNotFalse($acceptAt, "у $file не знайдено $accept - тест застарів");
        $this->assertNotFalse($writeAt, "у $file не знайдено $write - тест застарів");
        $this->assertLessThan($writeAt, $acceptAt, "$file пише в базу до перевірки повтору");
    }

    public static function guardedWriterProvider()
    {
        return [
            'callback' => [
                'Okay/Helpers/CommonHelper.php',
                'callback',
                '$this->acceptCallback(',
                '$callbacksEntity->add(',
            ],
            'feedback' => [
                'Okay/Controllers/FeedbackController.php',
                'feedback',
                '$this->acceptFeedback(',
                '$feedbacksEntity->add(',
            ],
            'comment' => [
                'Okay/Helpers/CommentsHelper.php',
                'comment',
                '$this->acceptComment(',
                '$commentsEntity->add(',
            ],
            'checkout' => [
                'Okay/Controllers/CartController.php',
                'checkout',
                '$this->acceptCheckout(',
                '$ordersHelper->add(',
            ],
            'fast_order' => [
                'Okay/Modules/OkayCMS/FastOrder/Controllers/FastOrderController.php',
                'fast_order',
                '$this->acceptFastOrder(',
                '$ordersEntity->add(',
            ],
        ];
    }

    /**
     * Токен видається на кожен рендер, тож дві вкладки кошика несуть різні
     * токени й за токеном обидві виглядають новими. Друга з них приходить уже
     * після того, як перша очистила кошик, - і саме порожній кошик її ловить.
     * На стенді без цієї перевірки друга вкладка створювала замовлення на 0.00
     * без жодної позиції.
     */
    public function testCheckoutRefusesAnEmptyCartBeforeCreatingAnOrder()
    {
        $source = $this->read('Okay/Controllers/CartController.php');

        $emptyAt  = strpos($source, '$cart->isEmpty');
        $acceptAt = strpos($source, '$this->acceptCheckout(');
        $writeAt  = strpos($source, '$ordersHelper->add(');

        $this->assertNotFalse($emptyAt, 'checkout не перевіряє, чи кошик порожній');
        $this->assertLessThan($acceptAt, $emptyAt, 'перевірка кошика має бути до зняття токена');
        $this->assertLessThan($writeAt, $emptyAt, 'checkout пише замовлення до перевірки кошика');
    }

    /**
     * Розширення мають викликатись до редиректу: Response::redirectTo()
     * завершується exit, тож усе після нього не виконується взагалі.
     */
    public function testExtendersRunBeforeTheRedirect()
    {
        $source = $this->read('Okay/Helpers/CommonHelper.php');

        $extenderAt = strpos($source, 'ExtenderFacade::execute(');
        $redirectAt = strpos($source, '$this->frontPostRedirectGet->redirectToCurrent()');

        $this->assertNotFalse($extenderAt);
        $this->assertNotFalse($redirectAt);
        $this->assertLessThan($redirectAt, $extenderAt, 'редирект з exit проковтне розширення');
    }

    /**
     * Підписка токена не має свідомо: повторний POST тим самим email рядка не
     * створює - його відсіює перевірка унікальності. Якщо колись відсіювання
     * приберуть, цей тест впаде і нагадає про токен.
     */
    public function testSubscribeStillRefusesADuplicateEmail()
    {
        $this->assertStringContainsString(
            "\$subscribesEntity->count(['email' => \$subscribe->email]) > 0",
            $this->read('Okay/Helpers/ValidateHelper.php')
        );
    }

    #[DataProvider('themeFormProvider')]
    public function testShippedThemesSubmitBothTokens($template, $form)
    {
        foreach (['okay_shop', 'vibe_shop'] as $theme) {
            $tpl = $this->read("design/{$theme}/html/{$template}");

            $this->assertStringContainsString('name="customer_csrf_token"', $tpl, "$theme/$template");
            $this->assertStringContainsString("{form_token name=\"{$form}\"}", $tpl, "$theme/$template");
        }
    }

    public static function themeFormProvider()
    {
        return [
            'callback'        => ['callback.tpl', 'callback'],
            'feedback'        => ['feedback.tpl', 'feedback'],
            'product comment' => ['product.tpl', 'comment'],
            'blog comment'    => ['post.tpl', 'comment'],
            'checkout'        => ['cart.tpl', 'checkout'],
        ];
    }

    /**
     * Форму підписки легко проґавити: токен їй доклеює JS. Але вона не має
     * action, тож без JS сабмітиться нативно - і без поля відпадає з 403.
     */
    #[DataProvider('subscribeTemplateProvider')]
    public function testSubscribeFormsCarryTheCsrfField($template)
    {
        foreach (['okay_shop', 'vibe_shop'] as $theme) {
            $this->assertStringContainsString(
                'name="customer_csrf_token"',
                $this->read("design/{$theme}/html/{$template}"),
                "$theme/$template"
            );
        }
    }

    public static function subscribeTemplateProvider()
    {
        return [
            'index'        => ['index.tpl'],
            'blog sidebar' => ['blog_sidebar.tpl'],
        ];
    }

    /**
     * Ajax-ендпоінт має відмовляти в тому ж форматі, що й відповідає: його
     * виклик читає відповідь як json і text/plain просто не розбере.
     */
    public function testFastOrderRefusesInJson()
    {
        $source = $this->read('Okay/Modules/OkayCMS/FastOrder/Controllers/FastOrderController.php');

        $this->assertStringContainsString('requireCustomerCsrf(true)', $source);
        $this->assertStringContainsString(
            'RESPONSE_JSON',
            $this->read('Okay/Core/Security/StorefrontGuard.php')
        );
    }

    public function testFastOrderFormSubmitsBothTokens()
    {
        $tpl = $this->read('Okay/Modules/OkayCMS/FastOrder/design/html/fast_order_form.tpl');

        $this->assertStringContainsString('name="customer_csrf_token"', $tpl);
        $this->assertStringContainsString('{form_token name="fast_order"}', $tpl);
    }

    private function read($file)
    {
        $path = dirname(__DIR__, 2) . '/' . $file;

        $this->assertFileExists($path);

        return file_get_contents($path);
    }
}
