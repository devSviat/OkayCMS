<?php


namespace Design;


use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/TemplateTagInventory.php';

/**
 * Smarty 5 компілює виклик функції в шаблоні через пошук модифікатора, тож
 * {date('Y-m-d')} потрапляє не в PHP date(), а в наш плагін із тегом `date`.
 * Плагін отримує формат замість дати, не розбирає його і повертає як є —
 * шаблон компілюється чисто, помилки немає, у фід їде рядок "Y-m-d".
 *
 * Compile-гейт такого не ловить структурно: синтаксис коректний. Тому тут окремо
 * забороняється писати наш тег у позиції виклику — лише через пайп.
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

        $source = file_get_contents(TemplateTagInventory::rootDir() . $relativePath);
        $code   = preg_replace('~\{\*.*?\*\}~s', '', $source);
        preg_match_all('~\{[^{}*][^{}]*\}~s', $code, $smartyTags);

        foreach ($smartyTags[0] as $tag) {
            foreach ($tags as $name) {
                $this->assertSame(
                    0,
                    preg_match('~(?<![\w>$.-])' . preg_quote($name, '~') . '\s*\(~', $tag),
                    "{$relativePath}: плагін `{$name}` викликано як функцію в {$tag}. "
                    . 'У Smarty 5 це піде в плагін, а не в однойменну функцію PHP.'
                );
            }
        }
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
