<?php


namespace TplMod;


use Okay\Core\Config;
use Okay\Core\TplMod\Parser;
use Okay\Core\TplMod\TplMod;

/**
 * A template that some module modifies is not compiled from disk. Design::applyTplModifiers
 * hands it to TplMod first, which re-parses the whole file into a node tree and prints it
 * back out. That round trip is allowed to change whitespace and nothing else.
 *
 * When it changes more, it drops the rest of the file from the point it lost its footing -
 * sometimes loudly, as a Smarty syntax error, sometimes silently, leaving a page rendered
 * half-empty with nothing in the log. Known ways to trip it: a `<` anywhere but an opening
 * {if} (so `le`/`lt` in {elseif}, {assign} and inline assignments), a tag whose name is a
 * Smarty variable, and an HTML tag opened inside one {if} branch and closed after {/if}.
 *
 * Which templates take that path depends on the modules a given shop has installed, so
 * every storefront and backend template is checked rather than a known few.
 *
 * Templates under Okay/Modules are out of scope on purpose: the XML feed fragments
 * (feed_head/feed_footer) are open-ended by design and cannot survive a balanced round trip.
 */
class ThemeTemplatesTplModTest extends \PHPUnit\Framework\TestCase
{
    private const EXCERPT_LENGTH = 80;

    /**
     * @dataProvider templatesDataProvider
     */
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

    public function templatesDataProvider(): array
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
        $configStub = $this->getMockBuilder(Config::class)->disableOriginalConstructor()->getMock();
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
