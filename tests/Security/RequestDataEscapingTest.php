<?php

namespace Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * request_data - це копія $_POST, яку шаблони друкують назад у поля форми,
 * щоб після помилки валідації користувач не набирав усе заново. Друк без
 * |escape дає XSS: у темі okay_shop поле відгуку віддавало текст у
 * <textarea> сирим, і "</textarea><img src=x onerror=...>" ставав живим
 * тегом на сторінці товару.
 */
class RequestDataEscapingTest extends TestCase
{
    #[DataProvider('templateProvider')]
    public function testRequestDataIsAlwaysEscaped($file)
    {
        $unescaped = [];

        foreach (file($file) as $number => $line) {
            // Умови ({if $request_data...}) не друкують нічого, тому лише друк.
            if (preg_match_all('/\{\$request_data[^}]*\}/u', $line, $matches)) {
                foreach ($matches[0] as $print) {
                    if (!str_contains($print, '|escape')) {
                        $unescaped[] = ($number + 1) . ': ' . trim($print);
                    }
                }
            }
        }

        $this->assertSame([], $unescaped, $this->relative($file) . ' друкує request_data без |escape');
    }

    public static function templateProvider()
    {
        $root = dirname(__DIR__, 2);

        $files = array_merge(
            glob($root . '/design/*/html/*.tpl') ?: [],
            glob($root . '/Okay/Modules/*/*/design/html/*.tpl') ?: [],
            glob($root . '/backend/design/html/*.tpl') ?: []
        );

        $cases = [];
        foreach ($files as $file) {
            if (str_contains(file_get_contents($file), '$request_data')) {
                $cases[str_replace($root . '/', '', $file)] = [$file];
            }
        }

        return $cases;
    }

    private function relative($file)
    {
        return str_replace(dirname(__DIR__, 2) . '/', '', $file);
    }
}
