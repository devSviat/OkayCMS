<?php

namespace Design;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Компілятор CSS адмінки склеює рядки правила **без пробілу**, тож селектор,
 * розбитий на кілька рядків, доходить до браузера як один компаунд:
 * `.show-tick .dropdown-menu li.selected a span.check-mark` ставав
 * `.show-tick.dropdown-menuli.selectedaspan.check-mark` і не збігався ні з чим.
 * Правило при цьому лишається в джерелі й виглядає робочим - помилка мовчазна.
 *
 * Перенос після коми безпечний: кома лишається в тексті селектора.
 */
class AdminCssSingleLineSelectorTest extends TestCase
{
    #[DataProvider('stylesheetProvider')]
    public function testNoSelectorSpansSeveralLines(string $file): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $file);
        $this->assertIsString($source, $file);

        $this->assertSame([], $this->brokenSelectors($source), $file);
    }

    public static function stylesheetProvider(): array
    {
        $files = glob(dirname(__DIR__, 2) . '/backend/design/css/*.css');
        $rows  = [];

        foreach ($files as $file) {
            $name = basename($file);
            $rows[$name] = ['backend/design/css/' . $name];
        }

        return $rows;
    }

    /**
     * Селектор - це текст між попередньою дужкою й наступною "{". Якщо в ньому
     * є перенос рядка, кожен рядок мусить закінчуватись комою.
     *
     * @return string[]
     */
    private function brokenSelectors(string $source): array
    {
        $source = preg_replace('~/\*.*?\*/~s', '', $source);
        $broken = [];
        $chunk  = '';

        for ($i = 0; $i < strlen($source); $i++) {
            $char = $source[$i];

            if ($char !== '{' && $char !== '}') {
                $chunk .= $char;
                continue;
            }

            if ($char === '{') {
                $selector = trim($chunk);
                $lines    = array_values(array_filter(array_map('trim', explode("\n", $selector))));

                if (count($lines) > 1 && $selector[0] !== '@') {
                    foreach (array_slice($lines, 0, -1) as $part) {
                        if (substr($part, -1) !== ',') {
                            $broken[] = implode(' ', $lines);
                            break;
                        }
                    }
                }
            }

            $chunk = '';
        }

        return $broken;
    }
}
