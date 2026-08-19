<?php

namespace Core;

use Okay\Core\BackendTranslations;
use Okay\Core\Modules\Modules;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Мови магазину і мови панелі - різні набори: у магазині може стояти будь-яка
 * з 31 мітки, а переклад панелі є лише для тих, чий файл лежить у backend/lang.
 * Сторінка входу вибирає мову до автентифікації, тому вибір мусить бути явним
 * і мусить відхиляти те, чого немає.
 */
class BackendTranslationsResolveLangTest extends TestCase
{
    public function testAvailableLanguagesAreTheTranslationFiles(): void
    {
        $available = $this->translations()->availableLanguages();

        $this->assertContains('en', $available);
        $this->assertContains('ru', $available);
        $this->assertContains('ua', $available);

        // languages_list.php - довідник міток, а не переклад.
        $this->assertNotContains('languages_list', $available);
    }

    public function testFirstPreferredWithATranslationWins(): void
    {
        $this->assertSame('ua', $this->translations()->resolveLang(['ua', 'en']));
        $this->assertSame('ru', $this->translations()->resolveLang(['ru', 'ua']));
    }

    /** Грузинський магазин - легальний, файла перекладу панелі для нього немає. */
    public function testLanguageWithoutATranslationIsSkipped(): void
    {
        $this->assertSame('en', $this->translations()->resolveLang(['ge', 'en']));
    }

    public function testFallsBackToEnglishWhenNothingMatches(): void
    {
        $this->assertSame('en', $this->translations()->resolveLang([]));
        $this->assertSame('en', $this->translations()->resolveLang(['ge', 'kz']));
    }

    /** Значення приходить із куки, тобто масивом теж. */
    public function testNonStringPreferenceIsIgnored(): void
    {
        $this->assertSame('en', $this->translations()->resolveLang([['ua'], null, 42]));
    }

    public function testTraversalInAPreferenceIsNotTreatedAsALanguage(): void
    {
        $this->assertSame('en', $this->translations()->resolveLang(['../../config/config']));
    }

    private function translations(): BackendTranslations
    {
        $modules = $this->createStub(Modules::class);
        $modules->method('getRunningModules')->willReturn([]);

        return new BackendTranslations(new NullLogger(), $modules);
    }
}
