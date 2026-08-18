<?php

namespace Core\TemplateConfig;

use Okay\Core\Modules\Module;
use Okay\Core\Modules\Modules;
use Okay\Core\TemplateConfig\BackendTemplateConfig;
use PHPUnit\Framework\TestCase;

/**
 * Каталог із параметра dir у {js} / {css} обрізався списком символів, записаним
 * в одинарних лапках: там \t\n\r\0\x0B - це літери, а не пробільні символи.
 * Шлях, що закінчувався будь-якою з них, вказував на неіснуючий каталог, і файл
 * мовчки не підключався.
 */
class TemplateConfigDirParamTest extends TestCase
{
    /** @var string */
    private $root;

    protected function setUp(): void
    {
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $_SERVER['SERVER_PORT'] = '80';
        $_SERVER['HTTP_HOST'] = 'okay.test';
        $_SERVER['REQUEST_URI'] = '/';

        $this->root = sys_get_temp_dir() . '/okay-dir-' . uniqid() . '/';
        mkdir($this->root . 'vendor/air-datepicker', 0777, true);
        mkdir($this->root . 'cache/css', 0777, true);
        file_put_contents($this->root . 'vendor/air-datepicker/widget.css', '.a{color:red}');
        file_put_contents($this->root . 'vendor/air-datepicker/widget.js', 'var a = 1;');
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->root);
    }

    public function testDirEndingWithTrimmedLetterIsKept(): void
    {
        $config = $this->makeConfig();

        $css = $config->compileIndividualCss('widget.css', 'vendor/air-datepicker');
        $js = $config->compileIndividualJs('widget.js', 'vendor/air-datepicker');

        $this->assertStringContainsString('<link', $css);
        $this->assertStringContainsString('<script', $js);
    }

    public function testLeadingAndTrailingSlashesAreStillTrimmed(): void
    {
        $config = $this->makeConfig();

        $this->assertStringContainsString('<link', $config->compileIndividualCss('widget.css', '/vendor/air-datepicker/'));
    }

    public function testMissingFileGivesEmptyString(): void
    {
        $config = $this->makeConfig();

        $this->assertSame('', $config->compileIndividualCss('nope.css', 'vendor/air-datepicker'));
    }

    private function makeConfig(): BackendTemplateConfig
    {
        // Заглушки, а не моки: конструктор їх лише зберігає, у цьому шляху вони не викликаються.
        return new BackendTemplateConfig(
            $this->createStub(Modules::class),
            $this->createStub(Module::class),
            $this->root,
            false,
            'theme-settings.css',
            $this->root . 'cache/css/',
            $this->root . 'cache/css/'
        );
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
