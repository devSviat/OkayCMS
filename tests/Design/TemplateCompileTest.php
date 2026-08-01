<?php


namespace Design;


use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/TemplateTagInventory.php';

/**
 * Кожен шаблон, що їде в поставці, має компілюватись двигуном із тим набором
 * тегів, який двигун реально реєструє.
 *
 * Це єдина перевірка, що ловить `unknown modifier` / `unknown tag`: решта тестів
 * шаблонів — це grep по вихідному тексту, а smoke.sh відкриває чотири GET-сторінки
 * однієї теми. Незареєстрований модифікатор інакше видно лише на живій сторінці.
 *
 * Тест змістовний на обох лініях Smarty: у v5 білим списком нативних функцій є
 * реєстрація, у v4 — політика безпеки; TemplateTagInventory налаштовує потрібне.
 */
class TemplateCompileTest extends TestCase
{
    private static $compileRoot;
    private static $smarty = [];

    public static function setUpBeforeClass(): void
    {
        self::$compileRoot = sys_get_temp_dir() . '/okaycms-compile-' . getmypid();
    }

    public static function tearDownAfterClass(): void
    {
        self::$smarty = [];
        self::removeDir(self::$compileRoot);
    }

    /**
     * @dataProvider templateProvider
     */
    public function testTemplateCompiles(string $surface, string $relativePath): void
    {
        $smarty = $this->smartyFor($surface);

        try {
            $smarty->createTemplate($relativePath)->compileTemplateSource();
        } catch (\Throwable $e) {
            $this->fail(sprintf(
                "%s (%s) не компілюється:\n%s: %s",
                $relativePath,
                $surface,
                get_class($e),
                $e->getMessage()
            ));
        }

        $this->assertTrue(true);
    }

    public function templateProvider(): array
    {
        $root = TemplateTagInventory::rootDir();
        $cases = [];

        foreach (self::surfaces() as $surface => $dirs) {
            foreach ($dirs['templates'] as $templateDir) {
                foreach (self::findTemplates($root . $templateDir) as $absolute) {
                    $relative = substr($absolute, strlen($root . $templateDir) + 1);
                    $cases["{$surface}: {$relative}"] = [$surface, $relative];
                }
            }
        }

        return $cases;
    }

    /**
     * Списки template_dir дзеркалять Design::setSmartyTemplatesDir(): шаблон модуля
     * шукається спершу в перевизначенні теми, далі у самому модулі, далі в темі.
     *
     * Обидві теми додані в хвіст модульних поверхонь, бо тема, під якою рендериться
     * модуль, залежить від налаштувань у БД, недоступних тесту.
     */
    private static function surfaces(): array
    {
        $surfaces = [];

        foreach (['okay_shop', 'vibe_shop'] as $theme) {
            $surfaces[$theme] = [
                'templates' => ["design/{$theme}/html"],
                'dirs'      => ["design/{$theme}/html"],
            ];
        }

        $surfaces['backend'] = [
            'templates' => ['backend/design/html'],
            'dirs'      => ['backend/design/html'],
        ];

        $surfaces['opensearch'] = [
            'templates' => ['Okay/xml'],
            'dirs'      => ['Okay/xml'],
        ];

        $root = TemplateTagInventory::rootDir();
        foreach (glob($root . 'Okay/Modules/*/*', GLOB_ONLYDIR) as $moduleDir) {
            $relative = substr($moduleDir, strlen($root));
            [$vendor, $module] = array_slice(explode('/', $relative), 2);

            $templateDirs = array_values(array_filter([
                "{$relative}/design/html",
                "{$relative}/Backend/design/html",
            ], static function ($dir) use ($root) {
                return is_dir($root . $dir);
            }));

            if ($templateDirs === []) {
                continue;
            }

            $surfaces["module {$vendor}/{$module}"] = [
                'templates' => $templateDirs,
                'dirs'      => array_merge(
                    ["design/okay_shop/modules/{$vendor}/{$module}/html"],
                    $templateDirs,
                    ['backend/design/html', 'design/okay_shop/html', 'design/vibe_shop/html']
                ),
            ];
        }

        return $surfaces;
    }

    private function smartyFor(string $surface)
    {
        if (!isset(self::$smarty[$surface])) {
            $root = TemplateTagInventory::rootDir();
            $dirs = array_map(static function ($dir) use ($root) {
                return $root . $dir;
            }, self::surfaces()[$surface]['dirs']);

            self::$smarty[$surface] = TemplateTagInventory::createSmarty(
                $dirs,
                self::$compileRoot . '/' . md5($surface)
            );
        }

        return self::$smarty[$surface];
    }

    /**
     * @return string[]
     */
    private static function findTemplates(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $found = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
            $dir,
            \FilesystemIterator::SKIP_DOTS
        ));

        foreach ($iterator as $file) {
            if ($file->isFile() && substr($file->getFilename(), -4) === '.tpl') {
                $found[] = $file->getPathname();
            }
        }

        sort($found);

        return $found;
    }

    private static function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }

        @rmdir($dir);
    }
}
