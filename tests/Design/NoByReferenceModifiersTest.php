<?php


namespace Design;


use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/SmartyTagScanner.php';

/**
 * Smarty передає модифікатору значення, а не посилання, тож reset(), key(), next(),
 * prev() і end() не працюють у шаблоні навіть зареєстровані — Smarty 5 каже це прямо.
 * У Smarty 4 вони мовчки працювали, тому в шаблонах і завелись.
 *
 * Заміни: |first і |first_key, обидва беруть значення з власної копії масиву.
 */
class NoByReferenceModifiersTest extends TestCase
{
    private const BY_REFERENCE = ['reset', 'key', 'next', 'prev', 'end', 'each'];

    #[DataProvider('templateProvider')]
    public function testTemplateHasNoByReferenceModifiers(string $relativePath): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $relativePath);
        $names  = implode('|', self::BY_REFERENCE);
        $found  = [];

        // Обидві перевірки дивляться лише всередину Smarty-тегів. Інакше сканування
        // сирого тексту падало б на прозі в {* коментарях *} і на JS-альтернаціях
        // на кшталт /start|end/, яких у шаблонах вистачає.
        foreach (SmartyTagScanner::tags($source) as $tag) {
            // Позиція модифікатора: {$x|reset}
            if (preg_match('~\|\s*@?(' . $names . ')\b~', $tag)) {
                $found[] = "модифікатор: {$tag}";
            }

            // Позиція виклику: {assign var=x value=reset($y)}. Лукбехайнд відсіює
            // jQuery на кшталт $(this).prev().
            if (preg_match('~(?<![\w>$.-])(' . $names . ')\s*\(~', $tag)) {
                $found[] = "виклик: {$tag}";
            }
        }

        $this->assertSame([], $found, "{$relativePath}: передача параметра за посиланням");
    }

    public static function templateProvider(): array
    {
        $root = dirname(__DIR__, 2) . '/';
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
