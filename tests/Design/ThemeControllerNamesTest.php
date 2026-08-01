<?php

namespace Design;

use PHPUnit\Framework\TestCase;

/**
 * Порівняння $controller з іменем, якого не носить жоден контролер, - мертва
 * гілка: вона ніколи не спрацьовує і мовчить. Саме так сторінка порівняння
 * втратила fancybox - product_list.tpl питав 'Comparison', тоді як роутер
 * кладе 'ComparisonController'.
 */
class ThemeControllerNamesTest extends TestCase
{
    private const DESIGN_DIR = __DIR__ . '/../../design';

    public function testEveryControllerComparisonNamesAnExistingController()
    {
        $templates = glob(self::DESIGN_DIR . '/*/html/*.tpl');
        $this->assertNotEmpty($templates, 'no theme templates found');

        $known = $this->knownControllers();
        $this->assertContains('ComparisonController', $known);

        $unknown = [];
        foreach ($templates as $file) {
            $matches = [];
            preg_match_all(
                '/\$controller\s*[!=]==?\s*[\'"]([A-Za-z_]+)[\'"]/',
                file_get_contents($file),
                $matches
            );

            foreach (array_unique($matches[1]) as $name) {
                if (!in_array($name, $known, true)) {
                    $unknown[] = substr($file, strlen(self::DESIGN_DIR) + 1) . ': ' . $name;
                }
            }
        }

        $this->assertSame([], $unknown, "Порівняння з неіснуючим контролером:\n" . implode("\n", $unknown));
    }

    private function knownControllers(): array
    {
        $names = [];
        $dirs = [
            __DIR__ . '/../../Okay/Controllers/*.php',
            __DIR__ . '/../../Okay/Modules/*/*/Controllers/*.php',
        ];

        foreach ($dirs as $pattern) {
            foreach (glob($pattern) as $file) {
                $names[] = basename($file, '.php');
            }
        }

        return $names;
    }
}
