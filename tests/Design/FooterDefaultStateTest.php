<?php

namespace Design;

use PHPUnit\Framework\TestCase;

/**
 * Стан за замовчуванням заданий у двох місцях: CSS вирішує, чи розгорнутий
 * блок, а клас на кнопці — куди дивиться шеврон. Розійдуться — стрілка
 * показуватиме навпаки, і жоден інший тест цього не побачить.
 */
class FooterDefaultStateTest extends TestCase
{
    public function testFooterBodyIsExpandedByDefault()
    {
        $css = $this->read('design/vibe_shop/css/components.css');

        $matched = preg_match('~\.vs-footer__body\s*\{(.*?)\}~s', $css, $m);
        $this->assertSame(1, $matched, '.vs-footer__body не знайдено');

        $this->assertStringNotContainsString('display: none', $m[1]);
    }

    public function testEveryToggleStartsInTheExpandedState()
    {
        $tpl = $this->read('design/vibe_shop/html/index.tpl');

        preg_match_all('~class="fn_switch_parent vs-footer__toggle[^"]*"~', $tpl, $m);

        $this->assertNotEmpty($m[0], 'перемикачі футера не знайдено');
        foreach ($m[0] as $class) {
            $this->assertStringContainsString('down', $class);
        }
    }

    /**
     * 44px лишається областю натискання, але вже як накладка: як власна
     * коробка вона відсувала гліф від рядка на ~16px з кожного боку.
     */
    public function testCopyrightMarkKeepsItsTouchTarget()
    {
        $css = $this->read('design/vibe_shop/css/components.css');

        $this->assertMatchesRegularExpression(
            '~\.vs-copyright__mark::after\s*\{[^}]*width:\s*44px~s',
            $css
        );
        $this->assertDoesNotMatchRegularExpression(
            '~\.vs-copyright__mark\s*\{[^}]*min-width:\s*44px~s',
            $css
        );
    }

    private function read($file)
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $file);
        $this->assertIsString($source, $file);

        return $source;
    }
}
