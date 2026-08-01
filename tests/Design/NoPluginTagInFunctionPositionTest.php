<?php


namespace Design;


use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/TemplateTagInventory.php';
require_once __DIR__ . '/SmartyTagScanner.php';

/**
 * Smarty 5 компілює виклик функції в шаблоні через пошук модифікатора, тож
 * {date('Y-m-d')} потрапляє не в PHP date(), а в наш плагін із тегом `date`.
 * Плагін отримує формат замість дати, не розбирає його і повертає як є —
 * шаблон компілюється чисто, помилки немає, у фід їде рядок "Y-m-d".
 *
 * Compile-гейт такого не ловить структурно: синтаксис коректний. Тому тут окремо
 * забороняється писати наш тег у позиції виклику — лише через пайп.
 *
 * Перевіряються лише теги-модифікатори, і це навмисно: плагін-функція, викликана
 * з дужками, у Smarty 5 не резолвиться взагалі й падає на компіляції, тобто її
 * ловить гейт. Тихий неправильний вивід дають саме модифікатори.
 */
class NoPluginTagInFunctionPositionTest extends TestCase
{
    /**
     * @dataProvider templateProvider
     */
    public function testNoModifierTagIsCalledAsFunction(string $relativePath): void
    {
        $tags = TemplateTagInventory::pluginTags()['modifier'];
        $this->assertNotEmpty($tags, 'інвентар модифікаторів порожній — тест утратив би сенс');

        $names = implode('|', array_map(static function (string $tag): string {
            return preg_quote($tag, '~');
        }, $tags));

        $source = file_get_contents(TemplateTagInventory::rootDir() . $relativePath);
        $found  = [];

        // Лукбехайнд відсіює $obj->date(, .date( і mydate(.
        foreach (SmartyTagScanner::tags($source) as $tag) {
            if (preg_match('~(?<![\w>$.-])(' . $names . ')\s*\(~', $tag, $match)) {
                $found[] = "`{$match[1]}` у {$tag}";
            }
        }

        $this->assertSame(
            [],
            $found,
            "{$relativePath}: плагін викликано як функцію. "
            . 'У Smarty 5 це піде в плагін, а не в однойменну функцію PHP.'
        );
    }

    public function templateProvider(): array
    {
        $root = TemplateTagInventory::rootDir();
        $cases = [];

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
            $root,
            \FilesystemIterator::SKIP_DOTS
        ));

        foreach ($iterator as $file) {
            $path = $file->getPathname();
            if (substr($path, -4) !== '.tpl' || strpos($path, $root . 'vendor/') === 0) {
                continue;
            }
            $relative = substr($path, strlen($root));
            $cases[$relative] = [$relative];
        }

        ksort($cases);

        return $cases;
    }
}
