<?php


namespace Design;


use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/TemplateTagInventory.php';

/**
 * У Smarty 4 присвоєння всередині {function} було видиме назовні, у Smarty 5 -
 * ні. Лічильник чи прапорець, який шаблон накопичує у {function} і читає після
 * неї, тихо збивається: розмітка лишається валідною, сторінка не падає, а
 * значення неправильні. Саме так backend/design/html/menu.tpl видавав два
 * однакових index, а контролер розкладає пункти меню в масив за index - і
 * збереження меню втрачало пункти.
 *
 * Компіляція такого не ловить, і жоден інший тест теж: помилки немає.
 * Тому вимога явна - або scope=, або значення не перетинає межу {function}.
 */
class NoCrossScopeFunctionVariableTest extends TestCase
{
    /**
     * @dataProvider templateProvider
     */
    public function testAssignmentInsideFunctionDoesNotLeakWithoutScope(string $relativePath): void
    {
        $source = file_get_contents(TemplateTagInventory::rootDir() . $relativePath);
        $found  = [];

        foreach (self::functionBodies($source) as [$body, $outside]) {
            foreach (self::assignedVariables($body) as $var) {
                if (preg_match('~\$' . preg_quote($var, '~') . '\b~', $outside)) {
                    $found[] = "\${$var}";
                }
            }
        }

        $this->assertSame(
            [],
            array_values(array_unique($found)),
            "{$relativePath}: змінна змінюється у {function} і читається зовні. "
            . 'У Smarty 5 зовні лишиться старе значення - потрібен явний scope= '
            . 'або {counter}.'
        );
    }

    /**
     * @return array<int, array{0: string, 1: string}> тіло кожної {function} і решта шаблону
     */
    private static function functionBodies(string $source): array
    {
        $bodies = [];
        if (preg_match_all('~\{function\s[^}]*\}(.*?)\{/function\}~s', $source, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $i => [$whole, $offset]) {
                $bodies[] = [
                    $matches[1][$i][0],
                    substr($source, 0, $offset) . substr($source, $offset + strlen($whole)),
                ];
            }
        }

        return $bodies;
    }

    /**
     * Присвоєння з явним scope= пропускаються: там межу перетинають навмисно.
     *
     * @return string[]
     */
    private static function assignedVariables(string $body): array
    {
        $vars = [];

        if (preg_match_all('~\{\s*assign\s+([^}]*)\}~', $body, $matches)) {
            foreach ($matches[1] as $args) {
                if (preg_match('~\bscope\s*=~', $args)) {
                    continue;
                }
                if (preg_match('~\bvar\s*=\s*["\']?(\w+)~', $args, $var)) {
                    $vars[] = $var[1];
                }
            }
        }

        // {$x = ...} і {$x++} - короткі форми того самого.
        if (preg_match_all('~\{\$(\w+)\s*(?:=[^=]|\+\+|--)~', $body, $matches)) {
            $vars = array_merge($vars, $matches[1]);
        }
        if (preg_match_all('~\$(\w+)(?:\+\+|--)~', $body, $matches)) {
            $vars = array_merge($vars, $matches[1]);
        }

        return array_unique($vars);
    }

    public static function templateProvider(): array
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
            if (strpos(file_get_contents($path), '{function ') === false) {
                continue;
            }
            $cases[$relative] = [$relative];
        }

        ksort($cases);

        return $cases;
    }
}
