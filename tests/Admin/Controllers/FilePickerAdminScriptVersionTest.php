<?php

namespace Admin\Controllers;

use PHPUnit\Framework\TestCase;

/**
 * Скрипт вибирача підключається прямим тегом, повз бандл, тож кеш-мітку
 * задає шаблон. З версією CMS браузер тримав стару копію після кожної правки
 * файла: розмітка вже нова, скрипт ще старий, і видалення питало через
 * window.confirm замість модалки.
 */
class FilePickerAdminScriptVersionTest extends TestCase
{
    public function testScriptIsVersionedByItsOwnModificationTime(): void
    {
        $template = $this->read('backend/design/html/file_picker.tpl');

        $this->assertStringContainsString('okay-file-picker.js?v={$picker_script_version', $template);
        $this->assertStringNotContainsString('okay-file-picker.js?v={$config->version', $template);

        $this->assertStringContainsString(
            "assign('picker_script_version', (int)@filemtime(",
            $this->read('backend/Controllers/FilePickerAdmin.php')
        );
    }

    private function read(string $relativePath): string
    {
        return file_get_contents(dirname(__DIR__, 3) . '/' . $relativePath);
    }
}
