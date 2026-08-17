<?php

namespace Admin;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Парсер HTML викидає один перенос рядка одразу після <textarea>. Поки вміст
 * файлу стоїть упритул до тега, редактор адмінки читає його вже без першого
 * рядка й таким зберігає на диск - від самого «Застосувати», без жодної правки.
 * Тому вміст мусить починатися з власного рядка: саме його парсер і з'їсть.
 */
class EditorTextareaLeadingNewlineTest extends TestCase
{
    public static function editors(): array
    {
        return [
            'шаблони' => ['templates.tpl', 'template_content'],
            'стилі'   => ['styles.tpl', 'content'],
            'скрипти' => ['scripts.tpl', 'script_content'],
            'robots'  => ['robots.tpl', 'robots'],
        ];
    }

    #[DataProvider('editors')]
    public function testFileContentStartsOnItsOwnLine(string $file, string $name): void
    {
        $template = file_get_contents(dirname(__DIR__, 2) . '/backend/design/html/' . $file);

        $this->assertMatchesRegularExpression(
            '/<textarea[^>]*name="' . $name . '"[^>]*>\n/',
            $template,
            $file . ': вміст стоїть упритул до тега, перший рядок файлу з\'їсть парсер'
        );
    }
}
