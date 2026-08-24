<?php

namespace Seo;

use Okay\Core\SmartyPlugins\Plugins\JsonLdText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Модифікатор готує текст статті до підстановки між лапками в JSON-LD.
 *
 * Керуючий символ усередині рядка робить увесь блок невалідним, і пошуковик
 * відкидає його цілком - на блозі це вся схема Article.
 */
class JsonLdTextTest extends TestCase
{
    private function wrap(string $raw): ?array
    {
        return json_decode('{"x":"' . (new JsonLdText())->run($raw) . '"}', true);
    }

    public static function hostileValues(): array
    {
        return [
            'CRLF з редактора' => ["перший рядок\r\nдругий"],
            'самий CR'         => ["перший\rдругий"],
            'табуляція'        => ["до\tпісля"],
            'лапки'            => ['текст із "лапками"'],
            'зворотний слеш'   => ['шлях C:\\temp\\file'],
            'закриття тега'    => ['текст </script><script>alert(1)</script>'],
            'битий UTF-8'      => ["Shop\xC3\x28"],
        ];
    }

    #[DataProvider('hostileValues')]
    public function testEveryValueStaysValidJson(string $raw): void
    {
        $this->assertIsArray($this->wrap($raw), 'блок JSON-LD став невалідним: ' . json_last_error_msg());
    }

    /**
     * Лапки мусять лишатись лапками. htmlspecialchars робив із них &quot;, а
     * усередині <script> сутності не декодуються - текст осідав зіпсованим.
     */
    public function testQuotesSurviveAsQuotes(): void
    {
        $this->assertSame('текст із "лапками"', $this->wrap('текст із "лапками"')['x']);
    }

    /**
     * Тег не має закриватись зсередини значення.
     */
    public function testScriptTagCannotBreakOut(): void
    {
        $this->assertStringNotContainsStringIgnoringCase('</script', (new JsonLdText())->run('a </script> b'));
    }

    public function testTagsAndSurroundingSpaceAreStripped(): void
    {
        $this->assertSame('текст', $this->wrap('  <p>текст</p>  ')['x']);
    }
}
