<?php

namespace Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * `gzip on` сам по собі не вмикає стиснення за проксі: без `gzip_proxied`
 * діє дефолт `off`, і nginx віддає сирим усе, що прийшло із заголовком `Via`.
 * Помилка мовчазна - відповідь коректна, просто в рази більша, - тож ловиться
 * лише прицільним запитом або оцим тестом.
 */
class NginxCompressionTest extends TestCase
{
    /** Конфіги, які мусять описувати те саме стиснення. */
    public static function configProvider(): array
    {
        return [
            'dev template' => ['dev/config/nginx/templates/default.conf.template'],
            'docs example' => ['docs/nginx/nginx.conf'],
        ];
    }

    #[DataProvider('configProvider')]
    public function testCompressionAppliesBehindAProxy(string $path): void
    {
        $config = $this->config($path);

        $this->assertMatchesRegularExpression(
            '~^\s*gzip\s+on;~m',
            $config,
            sprintf('%s: стиснення вимкнено зовсім', $path)
        );

        // Саме `не off`, а не «директива присутня»: `gzip_proxied off;` - це
        // той самий дефолт, тільки записаний явно, і перевірка на наявність
        // пропустила б його.
        preg_match('~^\s*gzip_proxied\s+([^;]+);~m', $config, $proxied);

        $this->assertNotEmpty(
            $proxied,
            sprintf(
                '%s: без gzip_proxied діє дефолт off — через Cloudflare чи Traefik '
                . 'відповіді підуть без стиснення',
                $path
            )
        );

        $this->assertNotSame(
            'off',
            trim($proxied[1]),
            sprintf('%s: gzip_proxied off — стиснення за проксі вимкнено', $path)
        );
    }

    /**
     * `gzip_vary` пропускає кеш проксі повз різні кодування: без нього
     * стисненою відповіддю нагодують клієнта, який gzip не просив.
     */
    #[DataProvider('configProvider')]
    public function testCompressedResponsesVaryOnEncoding(string $path): void
    {
        $this->assertMatchesRegularExpression(
            '~^\s*gzip_vary\s+on;~m',
            $this->config($path),
            sprintf('%s: gzip_vary вимкнено', $path)
        );
    }

    private function config(string $path): string
    {
        $full = dirname(__DIR__, 2) . '/' . $path;
        $this->assertFileExists($full);

        return (string)file_get_contents($full);
    }
}
