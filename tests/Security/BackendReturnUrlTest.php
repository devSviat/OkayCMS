<?php

namespace Security;

use Okay\Core\Security\BackendReturnUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Шаблони адмінки друкують href="{$smarty.get.return}" без екранування:
 * посилання з ?return="><script>… після «Зберегти» виконувало скрипт
 * у сесії менеджера.
 */
class BackendReturnUrlTest extends TestCase
{
    private const ROOT = 'https://shop.example';

    #[DataProvider('rejected')]
    public function testForeignOrScriptUrlIsDropped(mixed $value): void
    {
        self::assertNull(BackendReturnUrl::sanitize($value, self::ROOT));
    }

    public static function rejected(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'javascript з пробілом' => [' javascript:alert(1)'],
            'data' => ['data:text/html,<script>alert(1)</script>'],
            'вихід з атрибута' => ['"><script>alert(1)</script>'],
            'чужий хост' => ['https://evil.example/backend/'],
            'протокол-відносний' => ['//evil.example/'],
            'масив' => [['/backend/']],
            'порожньо' => [''],
        ];
    }

    public function testOwnUrlsStay(): void
    {
        $own = '/backend/index.php?controller=CategoriesAdmin&category_id=4';
        self::assertSame($own, BackendReturnUrl::sanitize($own, self::ROOT));
        self::assertSame(
            'https://shop.example/backend/index.php?controller=OrdersAdmin',
            BackendReturnUrl::sanitize('https://shop.example/backend/index.php?controller=OrdersAdmin', self::ROOT)
        );
    }

    /** Шлях свій, але в ньому розмітка: лишається посиланням, з атрибута не виходить. */
    public function testOwnPathCannotBreakTheAttribute(): void
    {
        $clean = BackendReturnUrl::sanitize('/backend/?k="><b onmouseover=\'x\'>`', self::ROOT);

        self::assertSame('/backend/?k=%22%3E%3Cb onmouseover=%27x%27%3E%60', $clean);
    }

    /** Без входу через backend/index.php санітайзер не діє на жоден шаблон. */
    public function testBackendEntrySanitizesBeforeControllersRun(): void
    {
        $entry = file_get_contents(dirname(__DIR__, 2) . '/backend/index.php');

        $sanitize = strpos($entry, 'BackendReturnUrl::sanitize($_GET[\'return\']');
        self::assertNotFalse($sanitize);
        self::assertLessThan(strpos($entry, '$managerMenu = $DI->get'), $sanitize);
    }
}
