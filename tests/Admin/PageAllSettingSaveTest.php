<?php

namespace Admin;

use PHPUnit\Framework\TestCase;

/**
 * Request::post() підставляє дефолт через empty(), тож переданий третім
 * аргументом він проковтнув би введений нуль - а нуль тут означає «не
 * показувати всі». Вимкнути page-all стало б неможливо, і ніде жодної помилки.
 *
 * Контролер тягне весь DI, тож перевіряємо джерело: рівно те місце, де вада
 * повертається одним зайвим аргументом.
 */
class PageAllSettingSaveTest extends TestCase
{
    private const CONTROLLER = 'backend/Controllers/SettingsIndexingAdmin.php';

    private function source(): string
    {
        $path = dirname(__DIR__, 2) . '/' . self::CONTROLLER;
        $this->assertFileExists($path, self::CONTROLLER . ' переїхав — тест треба оновити');

        return file_get_contents($path);
    }

    public function testZeroIsNotSwallowedByADefault(): void
    {
        $this->assertMatchesRegularExpression(
            "~post\(\s*'catalog_page_all_max_items'\s*,\s*'int'\s*\)~",
            $this->source(),
            'дефолт у post() перетворить нуль на нього — вимкнути page-all стане неможливо'
        );
    }

    public function testSettingIsSaved(): void
    {
        $this->assertMatchesRegularExpression(
            "~set\(\s*\n?\s*'catalog_page_all_max_items'~",
            $this->source(),
            'налаштування більше не зберігається'
        );
    }
}
