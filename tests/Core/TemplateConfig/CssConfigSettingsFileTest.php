<?php

namespace Core\TemplateConfig;

use Okay\Core\TemplateConfig\CssConfig;
use PHPUnit\Framework\TestCase;

/**
 * CssConfig::updateCssVariables() rewrites the theme settings file on every save
 * from Settings -> Theme. It prepends a fixed header comment, but the CSS parser
 * keeps the comments it read and the renderer emits them again, so each save used
 * to leave one more copy of that header behind - the file grew without limit.
 */
class CssConfigSettingsFileTest extends TestCase
{
    private const HEADER_MARKER = 'Файл стилей для настройки шаблона';

    /** @var string */
    private $settingsFile;

    protected function setUp(): void
    {
        $this->settingsFile = tempnam(sys_get_temp_dir(), 'okay-theme-settings-') . '.css';
        file_put_contents($this->settingsFile, <<<CSS
/**
* Файл стилей для настройки шаблона.
* Регистрировать этот файл для подключения в шаблоне не нужно
*/

:root {
\t--okay-button-color: #17171a;
\t--okay-basic-company: #17171a;
}

CSS
        );
    }

    protected function tearDown(): void
    {
        if ($this->settingsFile && file_exists($this->settingsFile)) {
            unlink($this->settingsFile);
        }
    }

    private function headerCount(): int
    {
        return substr_count(file_get_contents($this->settingsFile), self::HEADER_MARKER);
    }

    private function makeConfig(): CssConfig
    {
        return new CssConfig(dirname($this->settingsFile) . '/', $this->settingsFile);
    }

    public function testRepeatedSavesKeepExactlyOneHeader(): void
    {
        $this->assertSame(1, $this->headerCount(), 'fixture should start with one header');

        foreach (['#00a7ef', '#ff0000', '#00a7ef'] as $colour) {
            // A fresh instance per save: the admin builds one per request, and the
            // parsed-variable cache is per instance.
            $this->makeConfig()->updateCssVariables(['--okay-basic-company' => $colour]);
            $this->assertSame(1, $this->headerCount(), 'each save must leave exactly one header');
        }
    }

    public function testTheSubmittedValueIsWritten(): void
    {
        $this->makeConfig()->updateCssVariables(['--okay-basic-company' => '#00a7ef']);

        $this->assertStringContainsString('--okay-basic-company: #00a7ef', file_get_contents($this->settingsFile));
    }

    public function testVariablesThatWereNotSubmittedSurvive(): void
    {
        $this->makeConfig()->updateCssVariables(['--okay-basic-company' => '#00a7ef']);

        $this->assertStringContainsString('--okay-button-color: #17171a', file_get_contents($this->settingsFile));
    }
}
