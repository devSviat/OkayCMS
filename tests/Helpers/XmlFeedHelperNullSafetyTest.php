<?php

namespace Helpers;

use Okay\Helpers\XmlFeedHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Колонки товару, з яких збирається фід, здебільшого nullable: опис, артикул,
 * бренд, стара ціна, головна категорія. Кожне таке null, потрапивши у
 * strip_tags() чи в індекс масиву, друкує Deprecated - на проді в лог, а при
 * debug_mode ще й у тіло самого фіда.
 */
class XmlFeedHelperNullSafetyTest extends TestCase
{
    #[DataProvider('escapableProvider')]
    public function testEscapeAcceptsWhateverTheColumnHolds($value, $expected): void
    {
        $helper = $this->helper();

        $this->assertSame($expected, $helper->escape($value));
    }

    public static function escapableProvider()
    {
        return [
            'null'          => [null, ''],
            'empty string'  => ['', ''],
            'plain text'    => ['Диван', 'Диван'],
            'tags stripped' => ['<p>Диван</p>', 'Диван'],
            'entities'      => ['Ціна < 100 & "акція"', 'Ціна &lt; 100 &amp; &quot;акція&quot;'],
            'integer id'    => [17, '17'],
            'float price'   => [17.5, '17.5'],
        ];
    }

    /**
     * Товар без головної категорії: шаблони опису й анотації мусять дійти до
     * запасного варіанта, а не спіткнутись об індекс null.
     */
    #[DataProvider('templateMethodProvider')]
    public function testTemplateLookupSurvivesAProductWithoutCategory($method, $field, $fallback): void
    {
        $helper = $this->helper([
            'allCategories' => [7 => (object)['path' => [(object)[$field => 'з категорії']]]],
            'defaultProductsSeoPattern' => (object)[$field => $fallback],
        ]);

        $this->assertSame($fallback, $helper->$method((object)['main_category_id' => null]));
        $this->assertSame($fallback, $helper->$method((object)['main_category_id' => 0]));
        $this->assertSame($fallback, $helper->$method((object)['main_category_id' => 999]));
        $this->assertSame('з категорії', $helper->$method((object)['main_category_id' => 7]));
    }

    public static function templateMethodProvider()
    {
        return [
            'description' => ['getDescriptionTemplate', 'auto_description', 'запасний опис'],
            'annotation'  => ['getAnnotationTemplate', 'auto_annotation', 'запасна анотація'],
        ];
    }

    /**
     * Тест мусив би падати на Deprecated, а не мовчки його ковтати: у
     * phpunit.xml стоїть failOnDeprecation, але воно ловить лише те, що
     * PHPUnit бачить як власне сповіщення. Тому ловимо самі.
     */
    public function testNothingAboveEmitsADeprecation(): void
    {
        $seen = [];
        set_error_handler(function ($no, $str) use (&$seen) {
            $seen[] = $str;
            return true;
        }, E_ALL);

        try {
            $helper = $this->helper([
                'allCategories' => [],
                'defaultProductsSeoPattern' => (object)[],
            ]);

            $helper->escape(null);
            $helper->getDescriptionTemplate((object)['main_category_id' => null]);
            $helper->getAnnotationTemplate((object)['main_category_id' => null]);
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $seen, 'фід друкує в лог: ' . implode('; ', $seen));
    }

    /**
     * Конструктор ходить у базу по категорії й валюти, а перевіряти тут треба
     * рівно обробку null.
     */
    private function helper(array $state = []): XmlFeedHelper
    {
        $reflection = new ReflectionClass(XmlFeedHelper::class);
        $helper = $reflection->newInstanceWithoutConstructor();

        foreach ($state as $name => $value) {
            $reflection->getProperty($name)->setValue($helper, $value);
        }

        return $helper;
    }
}
