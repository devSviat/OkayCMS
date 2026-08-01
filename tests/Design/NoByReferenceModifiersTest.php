<?php


namespace Design;


use PHPUnit\Framework\TestCase;

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

    /**
     * @dataProvider templateProvider
     */
    public function testTemplateHasNoByReferenceModifiers(string $relativePath): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $relativePath);
        $names  = implode('|', self::BY_REFERENCE);

        // Позиція модифікатора: {$x|reset}
        $this->assertSame(
            0,
            preg_match_all('~\|\s*@?(' . $names . ')\b~', $source),
            "{$relativePath}: модифікатор із передачею за посиланням"
        );

        // Позиція виклику всередині Smarty-тега: {assign var=x value=reset($y)}.
        // Шукаємо лише в межах {...} і повз {* коментарі *}, щоб не чіпати ні
        // jQuery на кшталт $(this).prev(), ні згадки про нього в прозі.
        $code = preg_replace('~\{\*.*?\*\}~s', '', $source);
        preg_match_all('~\{[^{}*][^{}]*\}~s', $code, $tags);
        foreach ($tags[0] as $tag) {
            $this->assertSame(
                0,
                preg_match_all('~(?<![\w>$.-])(' . $names . ')\s*\(~', $tag),
                "{$relativePath}: виклик функції з передачею за посиланням у {$tag}"
            );
        }
    }

    public function templateProvider(): array
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
