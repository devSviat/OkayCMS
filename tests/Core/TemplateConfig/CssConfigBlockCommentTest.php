<?php

namespace Core\TemplateConfig;

use Okay\Core\TemplateConfig\CssConfig;
use PHPUnit\Framework\TestCase;

/**
 * Компілятор CSS вирізає блокові коментарі порядково. Код, що стояв у тому ж рядку
 * перед відкриттям коментаря, губився разом із коментарем — у мініфікованому файлі,
 * де банер ліцензії стоїть у кінці першого рядка, це з'їдало весь файл.
 */
class CssConfigBlockCommentTest extends TestCase
{
    /** @var string */
    private $dir;

    /** @var string */
    private $sourceFile;

    protected function setUp(): void
    {
        // Компілятор будує source map і питає в Request адресу сайту.
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $_SERVER['SERVER_PORT'] = '80';
        $_SERVER['HTTP_HOST'] = 'okay.test';
        $_SERVER['REQUEST_URI'] = '/';

        $this->dir = sys_get_temp_dir() . '/okay-css-' . uniqid() . '/';
        mkdir($this->dir);

        // Форма mini-файла: код, банер посеред рядка, код одразу після закриття коментаря.
        $this->sourceFile = $this->dir . 'source.css';
        file_put_contents($this->sourceFile, <<<CSS
.before{color:red}/*!
  Theme: Example
  Author: nobody
*/.after{color:blue}
CSS
        );
    }

    protected function tearDown(): void
    {
        // Компільовані файли починаються з крапки (префікс порожній), тож звичайний glob їх не бачить.
        foreach (glob($this->dir . '{,.}*', GLOB_BRACE) as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($this->dir);
    }

    private function compile(): string
    {
        $config = new CssConfig($this->dir, $this->dir . 'settings.css');

        return file_get_contents($config->compileIndividual($this->sourceFile, $this->dir));
    }

    public function testCodeBeforeAnInlineBlockCommentSurvives(): void
    {
        $this->assertStringContainsString('.before{color:red}', $this->compile());
    }

    public function testCodeAfterAnInlineBlockCommentSurvives(): void
    {
        $this->assertStringContainsString('.after{color:blue}', $this->compile());
    }

    public function testTheCommentItselfIsStripped(): void
    {
        $compiled = $this->compile();

        $this->assertStringNotContainsString('Theme: Example', $compiled);
        $this->assertStringNotContainsString('Author: nobody', $compiled);
    }
}
