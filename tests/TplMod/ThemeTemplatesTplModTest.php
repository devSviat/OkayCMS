<?php


namespace TplMod;


use Okay\Core\Config;
use Okay\Core\TplMod\Parser;
use Okay\Core\TplMod\TplMod;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Шаблон, який модифікує якийсь модуль, проходить через TplMod: той розбирає файл
 * у дерево вузлів і друкує назад. Цей обхід має міняти лише пробіли.
 *
 * Коли міняє більше — мовчки відкидає решту файлу, лишаючи половину сторінки без жодного
 * запису в лог. Відомі пастки: `<` будь-де, крім відкривального {if}; тег, чиє імʼя
 * є Smarty-змінною; HTML-тег, відкритий в одній гілці {if} і закритий після {/if}.
 *
 * Перевіряються всі шаблони вітрини й бекенду, бо набір модифікованих залежить від
 * встановлених модулів. Шаблони під Okay/Modules поза межами: XML-фрагменти фідів
 * навмисно незбалансовані.
 */
class ThemeTemplatesTplModTest extends \PHPUnit\Framework\TestCase
{
    private const EXCERPT_LENGTH = 80;

    #[DataProvider('templatesDataProvider')]
    public function testTemplateSurvivesTplModRoundTrip(string $relativePath)
    {
        $original = file_get_contents(self::rootDir() . DIRECTORY_SEPARATOR . $relativePath);
        $rebuilt  = $this->rebuild($original);

        $originalText = self::withoutWhitespace($original);
        $rebuiltText  = self::withoutWhitespace($rebuilt);

        if ($originalText !== $rebuiltText) {
            $divergedAt = self::firstDifference($originalText, $rebuiltText);

            $this->fail(sprintf(
                "%s does not survive TplMod: %d of %d characters lost, first divergence at %d.\n"
                . "  written:  ...%s...\n"
                . "  rebuilt:  ...%s...",
                $relativePath,
                max(0, strlen($originalText) - strlen($rebuiltText)),
                strlen($originalText),
                $divergedAt,
                self::excerpt($originalText, $divergedAt),
                self::excerpt($rebuiltText, $divergedAt)
            ));
        }

        $this->assertSame($originalText, $rebuiltText);
    }

    public static function templatesDataProvider(): array
    {
        $rootDir = self::rootDir();

        $paths = array_merge(
            (array)glob($rootDir . '/design/*/html/*.tpl'),
            (array)glob($rootDir . '/backend/design/html/*.tpl')
        );

        $cases = [];
        foreach ($paths as $path) {
            $relativePath = substr($path, strlen($rootDir) + 1);
            $cases[$relativePath] = [$relativePath];
        }

        return $cases;
    }

    private function rebuild(string $template): string
    {
        $configStub = $this->createStub(Config::class);
        $tplMod = new TplMod(new Parser(), $configStub);

        $methodBuild = (new \ReflectionClass(TplMod::class))->getMethod('build');

        return $methodBuild->invokeArgs($tplMod, [(new Parser())->parse($template)]);
    }

    private static function rootDir(): string
    {
        return dirname(__DIR__, 2);
    }

    private static function withoutWhitespace(string $template): string
    {
        return preg_replace('~\s+~', '', $template);
    }

    private static function firstDifference(string $written, string $rebuilt): int
    {
        $shared = min(strlen($written), strlen($rebuilt));
        for ($i = 0; $i < $shared; $i++) {
            if ($written[$i] !== $rebuilt[$i]) {
                return $i;
            }
        }

        return $shared;
    }

    private static function excerpt(string $text, int $at): string
    {
        return substr($text, max(0, $at - 10), self::EXCERPT_LENGTH);
    }
}
