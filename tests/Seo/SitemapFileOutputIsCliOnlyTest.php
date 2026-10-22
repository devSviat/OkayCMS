<?php

namespace Seo;

use Okay\Helpers\SiteMapHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Режим «file» стирає sitemap_*.xml у корені сайту й пише їх наново. Раніше його
 * вмикав будь-який анонімний GET /sitemap.xml?output=file.
 */
class SitemapFileOutputIsCliOnlyTest extends TestCase
{
    /** @dataProvider webSapiProvider */
    #[DataProvider('webSapiProvider')]
    public function testWebRequestNeverWritesFiles(string $sapi): void
    {
        // $argv із query string — так його заповнює веб-SAPI при register_argc_argv = On.
        $argv = ['root_url' => 'http://attacker.example', 'output' => 'file'];

        self::assertSame('browser', SiteMapHelper::outputMode($sapi, $argv));
        self::assertSame('browser', SiteMapHelper::outputMode($sapi, []));
    }

    public static function webSapiProvider(): array
    {
        return [
            'fpm-fcgi'   => ['fpm-fcgi'],
            'cgi-fcgi'   => ['cgi-fcgi'],
            'apache2'    => ['apache2handler'],
            'frankenphp' => ['frankenphp'],
        ];
    }

    /** Контроль: cron через CLI, як і раніше, пише файли. */
    public function testCliWithArgumentsWritesFiles(): void
    {
        self::assertSame('file', SiteMapHelper::outputMode('cli', ['root_url' => 'https://example.com']));
    }

    public function testCliWithoutArgumentsRendersToBrowser(): void
    {
        self::assertSame('browser', SiteMapHelper::outputMode('cli', []));
    }

    /** Перевірка вище нічого не дає, якщо конструктор знову читатиме $_GET напряму. */
    public function testHelperDoesNotReadOutputFromQueryString(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/Okay/Helpers/SiteMapHelper.php');

        self::assertStringNotContainsString('$_GET', $source);
        self::assertStringContainsString('self::outputMode(PHP_SAPI', $source);
    }
}
