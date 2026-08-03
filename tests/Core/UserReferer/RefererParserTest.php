<?php

namespace Core\UserReferer;

use Okay\Core\UserReferer\UserReferer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Snowplow\RefererParser\Parser;

/**
 * Довідник рефереров лежить у репозиторії (Okay/Core/UserReferer/data), а не в
 * пакеті, тож заміна парсера не має міняти класифікацію. Значення зняті з
 * snowplow/referer-parser 0.2.0 перед переходом на simplestats/referer-parser.
 */
class RefererParserTest extends TestCase
{
    #[DataProvider('refererProvider')]
    public function testClassificationIsStable($url, $known, $medium, $source): void
    {
        $parser  = new Parser(UserReferer::createConfigReader());
        $referer = $parser->parse($url, 'http://okaycms.loc/products/divan-redking');

        $this->assertSame($known, $referer->isKnown(), $url);
        $this->assertSame($medium, $referer->getMedium(), $url);
        $this->assertSame($source, $referer->getSource(), $url);
    }

    public static function refererProvider()
    {
        return [
            'google'     => ['https://www.google.com/search?q=divan', true,  'search',   'Google'],
            'bing'       => ['https://www.bing.com/search?q=x',       true,  'search',   'Bing'],
            'duckduckgo' => ['https://duckduckgo.com/?q=x',           true,  'search',   'DuckDuckGo'],
            'facebook'   => ['https://www.facebook.com/',             true,  'social',   'Facebook'],
            'twitter'    => ['https://t.co/abc',                      true,  'social',   'Twitter'],
            'gmail'      => ['https://mail.google.com/',              true,  'email',    'Gmail'],
            'сторонній'  => ['https://example.org/blog',              false, 'unknown',  null],
            'внутрішній' => ['http://okaycms.loc/catalog',            false, 'internal', null],
        ];
    }

    /**
     * Parser створюється через DI на кожному запиті без куки userReferer, тож
     * депрекація на PHP 8.4+ друкувалась до заголовків і збивала їх. Клас
     * вантажиться в окремому процесі: рефлексія не відрізняє implicit від
     * explicit nullable.
     */
    public function testParserHasNoImplicitNullableDeprecation(): void
    {
        $vendorDir = dirname((new \ReflectionClass(\Composer\Autoload\ClassLoader::class))->getFileName(), 2);

        $script = 'set_error_handler(function ($no, $str) {'
            . '    if (strpos($str, "Implicitly marking") !== false) { fwrite(STDERR, $str); exit(7); }'
            . '    return false;'
            . '}, E_ALL);'
            . 'require ' . var_export($vendorDir . '/autoload.php', true) . ';'
            . 'class_exists(' . var_export(Parser::class, true) . ');'
            . 'exit(0);';

        $cmd = escapeshellarg(PHP_BINARY) . ' -d opcache.enable_cli=0 -r ' . escapeshellarg($script) . ' 2>&1';
        exec($cmd, $output, $exitCode);

        $this->assertSame(
            0,
            $exitCode,
            Parser::class . ' emits an implicit-nullable deprecation on PHP 8.4+:' . PHP_EOL . implode(PHP_EOL, $output)
        );
    }
}
