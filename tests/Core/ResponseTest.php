<?php

namespace Core;

use Okay\Core\Adapters\Response\AdapterManager;
use Okay\Core\Response;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Guards against the PHP 8.4+ "Implicitly marking parameter as nullable"
 * deprecation in Okay\Core\Response. Compiled in a fresh subprocess that fails
 * fast if the deprecation fires (reflection cannot tell implicit from explicit).
 */
class ResponseTest extends TestCase
{
    public function testHasNoImplicitNullableDeprecation(): void
    {
        $vendorDir = dirname((new \ReflectionClass(\Composer\Autoload\ClassLoader::class))->getFileName(), 2);

        $script = 'set_error_handler(function ($no, $str) {'
            . '    if (strpos($str, "Implicitly marking") !== false) { fwrite(STDERR, $str); exit(7); }'
            . '    return false;'
            . '}, E_ALL);'
            . 'require ' . var_export($vendorDir . '/autoload.php', true) . ';'
            . 'class_exists(' . var_export(\Okay\Core\Response::class, true) . ');'
            . 'exit(0);';

        $cmd = escapeshellarg(PHP_BINARY) . ' -d opcache.enable_cli=0 -r ' . escapeshellarg($script) . ' 2>&1';
        exec($cmd, $output, $exitCode);

        $this->assertSame(
            0,
            $exitCode,
            'Okay\Core\Response emits an implicit-nullable deprecation on PHP 8.4+:' . PHP_EOL . implode(PHP_EOL, $output)
        );
    }

    /**
     * Драйвер дає типовий Content-Type, тож заданий викликачем мусить його
     * пережити. Доки не переживав, CSV експорту й ZIP бекапу банерів
     * оголошувались як text/html.
     */
    public function testExplicitContentTypeSurvivesTheAdapterDefault(): void
    {
        $response = $this->response();
        $response->addHeader('Content-Type: application/octet-stream');
        $response->sendHeaders();

        $this->assertSame(
            ['Content-Type: application/octet-stream'],
            $this->contentTypes($response)
        );
    }

    public function testAdapterSuppliesTheContentTypeWhenTheCallerDidNot(): void
    {
        $response = $this->response();
        $response->sendHeaders();

        $this->assertSame(
            ['Content-type: text/plain; charset=utf-8'],
            $this->contentTypes($response)
        );
    }

    /**
     * Cache-Control драйвера SSE — вимога транспорту, а не типове значення,
     * а адмінка ставить свій Cache-Control на кожному запиті.
     */
    public function testAdapterKeepsItsNonContentTypeHeaders(): void
    {
        $response = $this->response();
        $response->setContentType(RESPONSE_GPT_STREAM);
        $response->addHeader('Cache-Control: no-cache, must-revalidate');
        $response->sendHeaders();

        $this->assertContains('Cache-Control: no-cache', $this->headerLines($response));
    }

    /**
     * Другий sendHeaders() трапляється, коли шаблон фіда кинув виняток після
     * першого: Router ловить його й рендерить помилку тим самим об'єктом.
     * Адаптери тут без залежностей — Html тягне Design і, за ним, базу.
     */
    public function testSecondSendReplacesTheContentTypeOfThePreviousAdapter(): void
    {
        $response = $this->response();
        $response->sendHeaders();

        $response->setContentType(RESPONSE_XML);
        $response->sendHeaders();

        $this->assertSame(
            ['Content-type: text/xml; charset=UTF-8'],
            $this->contentTypes($response)
        );
    }

    private function response(): Response
    {
        // commitStatusCode() читає протокол із $_SERVER, а в CLI його немає.
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';

        $response = new Response(new AdapterManager(RESPONSE_HTML));
        $response->setContentType(RESPONSE_TEXT);

        return $response;
    }

    /** @return list<string> */
    private function headerLines(Response $response): array
    {
        $property = new ReflectionProperty(Response::class, 'headers');

        return array_column($property->getValue($response), 0);
    }

    /** @return list<string> */
    private function contentTypes(Response $response): array
    {
        return array_values(array_filter(
            $this->headerLines($response),
            static fn(string $line) => stripos($line, 'content-type:') === 0
        ));
    }
}
