<?php

namespace Core\QueryFactory;

use PHPUnit\Framework\TestCase;

/**
 * aura/sqlquery 3 прибрав позиційні привʼязки: другий аргумент where()/having() —
 * масив іменованих значень. Скалярний аргумент дає TypeError уже під час запиту,
 * а не при завантаженні класу, тож ані phpstan, ані звичайні тести його не бачать:
 * фільтр каталогу за характеристикою так і падав на сторінці з HTTP 200.
 */
class NoPositionalBindsTest extends TestCase
{
    private const METHODS = ['where', 'orWhere', 'having', 'orHaving'];

    public function testNoQueryConditionPassesAScalarSecondArgument(): void
    {
        $root = dirname(__DIR__, 3);
        $offenders = [];

        foreach (['Okay', 'backend'] as $dir) {
            foreach ($this->phpFiles($root . '/' . $dir) as $file) {
                // Обгортки самі й оголошують сигнатуру з масивом — їх пропускаємо.
                if (str_contains($file, '/Okay/Core/QueryFactory/')) {
                    continue;
                }

                foreach ($this->conditionCalls(file_get_contents($file)) as [$method, $line, $cond, $second]) {
                    $relative = substr($file, strlen($root) + 1);

                    // Позиційний плейсхолдер в умові — рівно те, чого трійка не вміє.
                    if ($this->hasPositionalPlaceholder($cond)) {
                        $offenders[] = "$relative:$line: ->$method() з позиційним ? в умові";
                        continue;
                    }

                    // Літерал другим аргументом: '...', 42, (string)$x, trim($x) тощо.
                    if ($second !== null
                        && !str_starts_with($second, '[')
                        && !str_starts_with($second, 'array(')
                        && !str_starts_with($second, '$')
                    ) {
                        $offenders[] = "$relative:$line: ->$method(..., $second)";
                    }
                }
            }
        }

        $this->assertSame([], $offenders, "Другим аргументом має бути масив іменованих привʼязок:\n" . implode("\n", $offenders));
    }

    /**
     * `?` шукається лише всередині рядкових літералів умови: поза ними це тернарний
     * оператор PHP, яким умова часто й складається ('parent_id' . ($x ? '>0' : '=0')).
     */
    private function hasPositionalPlaceholder(string $cond): bool
    {
        preg_match_all('~\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*"~', $cond, $literals);

        foreach ($literals[0] as $literal) {
            if (str_contains($literal, '?')) {
                return true;
            }
        }

        return false;
    }

    /** @return list<array{0: string, 1: int, 2: string, 3: ?string}> */
    private function conditionCalls(string $source): array
    {
        $pattern = '~->(' . implode('|', self::METHODS) . ')\s*\(~';
        $calls = [];

        if (!preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE)) {
            return $calls;
        }

        foreach ($matches[0] as $i => [$text, $offset]) {
            $args = $this->splitArgs($source, $offset + strlen($text));
            $calls[] = [
                $matches[1][$i][0],
                substr_count(substr($source, 0, $offset), "\n") + 1,
                trim($args[0] ?? ''),
                isset($args[1]) ? trim($args[1]) : null,
            ];
        }

        return $calls;
    }

    /** @return list<string> */
    private function splitArgs(string $source, int $from): array
    {
        $depth = 1;
        $args = [''];

        for ($i = $from, $len = strlen($source); $i < $len && $depth > 0; $i++) {
            $char = $source[$i];

            if (str_contains('([{', $char)) {
                $depth++;
            } elseif (str_contains(')]}', $char)) {
                $depth--;
                if ($depth === 0) {
                    break;
                }
            }

            if ($depth === 1 && $char === ',') {
                $args[] = '';
            } else {
                $args[array_key_last($args)] .= $char;
            }
        }

        return $args;
    }

    /** @return list<string> */
    private function phpFiles(string $dir): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));

        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
