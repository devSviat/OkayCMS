<?php

namespace Security;

use Okay\Admin\Helpers\BackendFilePickerHelper;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;

/**
 * Завантажене через вибирач файлів не має ані виконуватись, ані рендеритись
 * на домені магазину. Межа тримається двома незалежними шарами: білий список
 * розширень у помічнику і deny-правила веб-сервера.
 */
class UploadedContentIsInertTest extends TestCase
{
    /**
     * Розширення, які браузер трактує як активний контент. Потрапивши в
     * files/, .html дає stored XSS на домені магазину — тобто доступ до
     * сесій покупців.
     */
    private const ACTIVE_WEB_EXTENSIONS = [
        'html', 'htm', 'xhtml', 'xml', 'xsl', 'js', 'css', 'shtml', 'phtml',
    ];

    #[DataProvider('allowlistProvider')]
    public function testActiveWebContentCannotBeUploaded($key)
    {
        $extensions = $this->allowlist($key);

        foreach (self::ACTIVE_WEB_EXTENSIONS as $extension) {
            $this->assertNotContains($extension, $extensions, $key . ' дозволяє .' . $extension);
        }
    }

    #[DataProvider('allowlistProvider')]
    public function testExtensionlessFilesAreNotAllowed($key)
    {
        $this->assertNotContains('', $this->allowlist($key), $key . ' дозволяє файл без розширення');
    }

    public static function allowlistProvider()
    {
        return [
            'images'    => ['IMAGE_EXTENSIONS'],
            'media'     => ['MEDIA_EXTENSIONS'],
            'documents' => ['DOCUMENT_EXTENSIONS'],
        ];
    }

    /** Санітайзер вибирача діє лише на svg, тож деінде його бути не має. */
    public function testSvgIsAcceptedAsAnImageOnly()
    {
        $this->assertContains('svg', $this->allowlist('IMAGE_EXTENSIONS'));
        $this->assertNotContains('svg', $this->allowlist('MEDIA_EXTENSIONS'));
        $this->assertNotContains('svg', $this->allowlist('DOCUMENT_EXTENSIONS'));
    }

    /** Єдине місце, де svg переписується, - завантаження у вибирачі. */
    public function testUploadedSvgGoesThroughTheSanitizer()
    {
        $source = $this->read('backend/Helpers/BackendFilePickerHelper.php');

        $this->assertStringContainsString('SvgSanitizer', $source);
        $this->assertStringContainsString("\$extension === 'svg'", $source);
    }

    /**
     * nginx зіставляє регулярку location з URI, а URI завжди починається зі
     * слеша. Прив'язка ^files/ не збігається ніколи, тому deny-правило, що
     * виглядає робочим, мовчки не діє.
     */
    #[DataProvider('nginxConfigProvider')]
    public function testLocationRegexesAreAnchoredToTheUriRoot($file)
    {
        $source = $this->read($file);

        preg_match_all('#location\s+~\*?\s+\^([A-Za-z0-9_])#', $source, $matches, PREG_OFFSET_CAPTURE);

        $broken = [];
        foreach ($matches[1] as $match) {
            $broken[] = 'рядок ' . (substr_count(substr($source, 0, $match[1]), "\n") + 1);
        }

        $this->assertSame([], $broken, $file . ': ^ без /, правило не збігається ніколи');
    }

    #[DataProvider('nginxConfigProvider')]
    public function testUploadDirectoriesDenyActiveContent($file)
    {
        $source = $this->read($file);

        foreach (['^/files/', '^/design/'] as $prefix) {
            $this->assertStringContainsString($prefix, $source, $file . ': нема deny для ' . $prefix);
        }
    }

    public static function nginxConfigProvider()
    {
        return [
            'docs' => ['docs/nginx/nginx.conf'],
        ];
    }

    /**
     * Той самий клас бага, що й у nginx вище: path_regexp у Caddy теж
     * зіставляється зі шляхом запиту, який завжди починається зі слеша.
     */
    public function testCaddyPathRegexesAreAnchoredToTheUriRoot()
    {
        $source = $this->read(self::CADDYFILE);

        preg_match_all('#path_regexp\s+(?:\S+\s+)?\^([A-Za-z0-9_])#', $source, $matches, PREG_OFFSET_CAPTURE);

        $broken = [];
        foreach ($matches[1] as $match) {
            $broken[] = 'рядок ' . (substr_count(substr($source, 0, $match[1]), "\n") + 1);
        }

        $this->assertSame([], $broken, self::CADDYFILE . ': ^ без /, правило не збігається ніколи');
    }

    public function testCaddyfileDeniesActiveContentInUploadDirectories()
    {
        $source = $this->read(self::CADDYFILE);

        foreach (['^/files/', '^/design/'] as $prefix) {
            $this->assertStringContainsString($prefix, $source, self::CADDYFILE . ': нема правила для ' . $prefix);
        }
    }

    private const CADDYFILE = 'dev/config/caddy/Caddyfile';

    /**
     * @return string[]
     */
    private function allowlist($constant)
    {
        $constants = (new ReflectionClass(BackendFilePickerHelper::class))->getConstants();

        $this->assertArrayHasKey($constant, $constants);

        return $constants[$constant];
    }

    private function read($file)
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $file);
        $this->assertIsString($source, $file);

        return $source;
    }
}
