<?php

namespace Security;

use PHPUnit\Framework\TestCase;

/**
 * Редактори теми в адмінці склеюють значення із запиту з каталогом теми.
 * Кожна з цих точок раніше не мала жодного відсіву шляху.
 */
class AdminFileEditorTraversalTest extends TestCase
{
    /**
     * @dataProvider guardedCallSiteProvider
     */
    public function testRequestSuppliedNamesGoThroughSafeFileName($file, array $mustContain)
    {
        $source = $this->source($file);

        foreach ($mustContain as $needle) {
            $this->assertStringContainsString($needle, $source, "{$file}: очікували {$needle}");
        }
    }

    public function guardedCallSiteProvider()
    {
        return [
            'images: delete/rename/upload' => [
                'backend/Controllers/ImagesAdmin.php',
                [
                    'use Okay\Core\Security\SafeFileName;',
                    "SafeFileName::basename(\$this->request->post('delete_image'))",
                    "SafeFileName::basename(\$images['name'][\$i])",
                ],
            ],
            'themes: delete/rename' => [
                'backend/Controllers/ThemeAdmin.php',
                [
                    'use Okay\Core\Security\SafeFileName;',
                    "SafeFileName::themeName(\$this->request->post('theme'))",
                ],
            ],
            'template editor' => [
                'backend/Controllers/TemplatesAdmin.php',
                ['SafeFileName::basename('],
            ],
            'script editor' => [
                'backend/Controllers/ScriptsAdmin.php',
                ["SafeFileName::basename(\$this->request->get('file'))"],
            ],
        ];
    }

    /**
     * Порожнє ім'я теми дало б dirDelete('design/') — тобто видалення
     * усіх тем одразу, тому дія має відсіюватись до switch.
     */
    public function testEmptyThemeNameCancelsTheAction()
    {
        $source = $this->source('backend/Controllers/ThemeAdmin.php');

        $this->assertMatchesRegularExpression(
            '/if \(\$action_theme === \'\'\) \{\s*\$action = null;/',
            $source
        );
    }

    /**
     * Небезпечні імена мають приводити до пропуску ітерації, а не до
     * склеювання порожнього рядка з каталогом.
     */
    public function testRenameSkipsUnusableNames()
    {
        foreach (['backend/Controllers/ImagesAdmin.php', 'backend/Controllers/ThemeAdmin.php'] as $file) {
            $this->assertMatchesRegularExpression(
                '/if \(\$old_name === \'\' \|\| \$new_name === \'\'\) \{\s*continue;/',
                $this->source($file),
                $file
            );
        }
    }

    /**
     * Старий фільтр був trim($name, '.') — знімав лише крайні крапки й
     * пропускав "a/../../..". Він не має повернутись.
     */
    public function testOldTrimOnlyFilterIsGone()
    {
        $source = $this->source('backend/Controllers/ImagesAdmin.php');

        $this->assertStringNotContainsString("trim(\$this->request->post('delete_image'), '.')", $source);
        $this->assertStringNotContainsString("trim(\$images['name'][\$i], '.')", $source);
    }

    private function source($relativePath)
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $relativePath);
        $this->assertIsString($source, $relativePath);

        return $source;
    }
}
