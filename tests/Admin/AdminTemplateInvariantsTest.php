<?php

namespace Admin;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Три тихі поломки в шаблонах адмінки, кожна з яких нічого не ламала помітно:
 * англійські підписи селектів, попередження PHP у тілі сторінки і другий
 * календар поверх нативного вибору дати на телефоні.
 */
class AdminTemplateInvariantsTest extends TestCase
{
    /**
     * bootstrap-select зливає $.fn.selectpicker.defaults поверх своїх типових
     * значень, але сам цього об'єкта не створює. Присвоєння по полю
     * ($.fn.selectpicker.defaults.noneSelectedText = …) падає з
     * "Cannot set properties of undefined", і підписи лишаються англійськими.
     */
    public function testSelectpickerDefaultsAreAssignedAsAWholeObject(): void
    {
        $source = $this->read('backend/design/html/index.tpl');

        $this->assertStringContainsString('$.fn.selectpicker.defaults = {', $source);
        $this->assertStringNotContainsString('$.fn.selectpicker.defaults.', $source);
    }

    #[DataProvider('languageFileProvider')]
    public function testSelectpickerStringsExistInEveryLanguage(string $file): void
    {
        $source = $this->read($file);

        foreach (['general_select_none', 'general_select_count', 'general_select_all', 'general_select_none_action'] as $key) {
            $this->assertStringContainsString("\$lang['" . $key . "']", $source, $file . ' -> ' . $key);
        }
    }

    public static function languageFileProvider(): array
    {
        return [
            'ua' => ['backend/lang/ua.php'],
            'ru' => ['backend/lang/ru.php'],
            'en' => ['backend/lang/en.php'],
        ];
    }

    /**
     * AuthorsHelper::getSocials() віддає [1] для автора без соцмереж, тож
     * $social - число, у нього немає domain, і ключ масиву стає null. PHP 8.5
     * друкує на це deprecation просто в тіло сторінки.
     */
    public function testAuthorSocialIconLookupHasNoNullKey(): void
    {
        $source = $this->read('backend/design/html/author.tpl');

        $this->assertStringContainsString("\$social_icons[\$social.domain|default:'']", $source);
        $this->assertStringNotContainsString('$social_icons[$social.domain]', $source);
    }

    /**
     * На телефоні поле дати віддається як type="date", тобто вибір малює сам
     * браузер. Виклик календаря поруч давав два UI на одному полі.
     */
    #[DataProvider('dateTemplateProvider')]
    public function testDatepickerIsNotAttachedOnMobile(string $file): void
    {
        $source = $this->read($file);

        $condition = strpos($source, '{if !$is_mobile && !$is_tablet}');
        $call      = strpos($source, "okayDatepicker('input[name=");

        $this->assertIsInt($condition, $file . ': немає умови про мобільний');
        $this->assertIsInt($call, $file . ': немає виклику календаря');
        $this->assertLessThan($call, $condition, $file . ': виклик поза умовою');
    }

    public static function dateTemplateProvider(): array
    {
        return [
            'reportstats'    => ['backend/design/html/reportstats.tpl'],
            'category_stats' => ['backend/design/html/category_stats.tpl'],
        ];
    }

    private function read(string $relativePath): string
    {
        return file_get_contents(dirname(__DIR__, 2) . '/' . $relativePath);
    }
}
