<?php

namespace Security;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Завантажене через файловий менеджер не має ані виконуватись, ані рендеритись
 * на домені магазину. Межа тримається двома незалежними шарами: allowlist
 * розширень у самому менеджері і deny-правила веб-сервера.
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

    #[DataProvider('creationAllowlistProvider')]
    public function testActiveWebContentCannotBeUploadedOrCreated($key)
    {
        $extensions = $this->allowlist($key);

        foreach (self::ACTIVE_WEB_EXTENSIONS as $extension) {
            $this->assertNotContains($extension, $extensions, $key . ' дозволяє .' . $extension);
        }
    }

    public static function creationAllowlistProvider()
    {
        return [
            'upload'  => ['ext_file'],
            'edit'    => ['editable_text_file_exts'],
            'preview' => ['previewable_text_file_exts'],
        ];
    }

    /**
     * Порожній рядок у списку означає "файл без розширення" і проходить повз
     * будь-яку перевірку за розширенням.
     */
    #[DataProvider('everyAllowlistProvider')]
    public function testExtensionlessFilesAreNotAllowed($key)
    {
        $this->assertNotContains('', $this->allowlist($key), $key . ' дозволяє файл без розширення');
    }

    public static function everyAllowlistProvider()
    {
        return [
            'images'    => ['ext_img'],
            'files'     => ['ext_file'],
            'video'     => ['ext_video'],
            'music'     => ['ext_music'],
            'misc'      => ['ext_misc'],
            'edit'      => ['editable_text_file_exts'],
            'preview'   => ['previewable_text_file_exts'],
        ];
    }

    /**
     * SVG лишається дозволеним для завантаження - його чистить SvgSanitizer -
     * але не для створення й редагування текстом, бо там санітайзер не діє.
     */
    public function testSvgIsUploadableAsAnImageOnly()
    {
        $this->assertContains('svg', $this->allowlist('ext_img'));
        $this->assertNotContains('svg', $this->allowlist('ext_file'));
        $this->assertNotContains('svg', $this->allowlist('editable_text_file_exts'));
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

    /**
     * Саме правило теж має існувати - анкер можна полагодити, а список
     * розширень загубити.
     */
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
            'dev'  => ['dev/config/nginx/templates/default.conf.template'],
            'docs' => ['docs/nginx/nginx.conf'],
        ];
    }

    /**
     * Значення читається з літерала в config.php: сам файл підключати не
     * можна - він тягне Request і залежить від оточення веб-запиту.
     *
     * @return string[]
     */
    private function allowlist($key)
    {
        $source = $this->read('backend/design/js/filemanager/config/config.php');

        $matched = preg_match(
            "#'" . preg_quote($key, '#') . "'\s*=>\s*array\((.*?)\)#s",
            $source,
            $matches
        );
        $this->assertSame(1, $matched, 'не знайдено ' . $key . ' у config.php');

        preg_match_all('#[\'"]([^\'"]*)[\'"]#', $matches[1], $values);

        return $values[1];
    }

    private function read($file)
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $file);
        $this->assertIsString($source, $file);

        return $source;
    }
}
