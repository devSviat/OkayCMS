<?php

namespace Admin;

use PHPUnit\Framework\TestCase;

/**
 * Селект мови панелі в формі менеджера не має жодного `selected`, тож типовий
 * вибір для нового менеджера дає просто перший пункт. Порядок пунктів приходить
 * із languages_list.php, і поки він був за англійською назвою, новий менеджер
 * українського магазину отримував англійську панель.
 */
class ManagerLanguageDefaultTest extends TestCase
{
    public function testFirstTranslatableLanguageIsUkrainian(): void
    {
        $langs = $this->languagesList();

        $translatable = array_filter(
            array_keys($langs),
            fn ($label) => file_exists(dirname(__DIR__, 2) . '/backend/lang/' . $label . '.php')
        );

        $this->assertSame('ua', reset($translatable));
    }

    /** Перестановка не мала нікого загубити. */
    public function testCatalogueStillHoldsEveryLanguage(): void
    {
        $labels = array_keys($this->languagesList());

        sort($labels);
        $this->assertCount(30, $labels);
        $this->assertContains('ge', $labels);
        $this->assertContains('en', $labels);
        $this->assertContains('ru', $labels);
    }

    private function languagesList(): array
    {
        $langs = [];
        require dirname(__DIR__, 2) . '/backend/lang/languages_list.php';

        return $langs;
    }
}
