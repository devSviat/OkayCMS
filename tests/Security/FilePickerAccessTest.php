<?php

namespace Security;

use Okay\Admin\Controllers\FilePickerAdmin;
use Okay\Admin\Controllers\IndexAdmin;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Вибирач файлів навмисно не має власного дозволу: редактор відкривають
 * менеджери з різними правами, і попередній менеджер файлів пускав так само -
 * будь-якого авторизованого. Ціна цього в тому, що обхід перевірки дозволів
 * у IndexAdmin::onInit не має розростатись, а автентифікація й CSRF мусять
 * лишатись на місці.
 */
class FilePickerAccessTest extends TestCase
{
    public function testPickerGoesThroughTheAdminControllerBase(): void
    {
        $this->assertTrue(
            (new ReflectionClass(FilePickerAdmin::class))->isSubclassOf(IndexAdmin::class),
            'поза IndexAdmin контролер лишиться без перевірки менеджера'
        );
    }

    public function testOnlyTheLoginPageAndThePickerSkipThePermissionCheck(): void
    {
        $source = $this->source('backend/Controllers/IndexAdmin.php');

        // Умова, у якій вирішується доступ: від "if" до виклику access().
        $found = preg_match('~if \(([^;]*?\$this->managers->access\()~s', $source, $condition);
        $this->assertSame(1, $found, 'перевірку дозволу в IndexAdmin не знайдено');

        preg_match_all('~backendController\s*[!=]==?\s*[\'"]([A-Za-z]+)[\'"]~', $condition[1], $matches);

        $this->assertSame(['AuthAdmin', 'FilePickerAdmin'], $matches[1]);
    }

    public function testCsrfGateStillCoversThePicker(): void
    {
        $source = $this->source('backend/index.php');

        preg_match_all("~\\\$backendControllerName\s*!==\s*'([A-Za-z]+)'~", $source, $matches);

        $this->assertSame(['AuthAdmin'], $matches[1], 'з-під CSRF-перевірки виключений лише вхід');
    }

    private function source(string $relativePath): string
    {
        return file_get_contents(dirname(__DIR__, 2) . '/' . $relativePath);
    }
}
