<?php

namespace Core\UserReferer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * referer_source - це назва джерела від парсера (Facebook, ChatGPT), а не URL;
 * хостом він буває лише тоді, коли реферер довіднику невідомий. Тож посилання
 * з нього не збудуєш: у картці замовлення воно вело на /backend/Facebook.
 */
class AdminRefererTagTest extends TestCase
{
    #[DataProvider('templateProvider')]
    public function testSourceNeverBecomesAHref($template): void
    {
        $this->assertSame(
            0,
            preg_match('/href="[^"]*referer_source/', $this->read($template)),
            $template . ': referer_source не URL, посиланням його робити не можна'
        );
    }

    #[DataProvider('templateProvider')]
    public function testEveryChannelStillShowsTheSource($template): void
    {
        $source = $this->read($template);

        $this->assertSame(
            5,
            substr_count($source, 'title="{$order->referer_source|escape}"'),
            $template . ': джерело має лишатись у title кожного з п\'яти каналів'
        );
    }

    public static function templateProvider()
    {
        return [
            'order'         => ['backend/design/html/order.tpl'],
            'orders'        => ['backend/design/html/orders.tpl'],
            'order_history' => ['backend/design/html/order_history.tpl'],
        ];
    }

    private function read($template)
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/' . $template);
        $this->assertIsString($source, $template);

        return $source;
    }
}
