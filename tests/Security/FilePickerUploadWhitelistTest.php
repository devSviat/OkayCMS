<?php

namespace Security;

use Okay\Admin\Helpers\BackendFilePickerHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Вибирач приймає файли в каталог, який сервер віддає по HTTP за власним
 * білим списком розширень. Розширити список вибирача, не розширивши серверний,
 * означає прийняти файл, якого браузер потім не побачить; розширити серверний
 * під вибирач - відкрити назовні новий тип. Тест тримає обидва боки разом.
 */
class FilePickerUploadWhitelistTest extends TestCase
{
    #[DataProvider('serverConfigProvider')]
    public function testEveryAcceptedExtensionIsServedBack(string $path): void
    {
        $extra = array_diff($this->pickerExtensions(), $this->serverExtensions($path));

        $this->assertSame(
            [],
            array_values($extra),
            "вибирач приймає розширення, яких {$path} не віддає"
        );
    }

    public static function serverConfigProvider(): array
    {
        return [
            'nginx'    => ['docs/nginx/nginx.conf'],
            'htaccess' => ['.htaccess'],
        ];
    }

    public function testNoExecutableExtensionIsAccepted(): void
    {
        $executable = ['php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar',
                       'html', 'htm', 'shtml', 'cgi', 'pl', 'py', 'sh', 'htaccess'];

        $this->assertSame([], array_values(array_intersect($this->pickerExtensions(), $executable)));
    }

    /** Усі три списки помічника разом - саме те, що приймає завантаження. */
    private function pickerExtensions(): array
    {
        $constants = (new ReflectionClass(BackendFilePickerHelper::class))->getConstants();

        $extensions = array_merge(
            $constants['IMAGE_EXTENSIONS'],
            $constants['MEDIA_EXTENSIONS'],
            $constants['DOCUMENT_EXTENSIONS']
        );

        sort($extensions);

        return $extensions;
    }

    /**
     * Обидва конфіги перелічують дозволене для /files/ однією альтернацією,
     * у якій `jpe?g` це два розширення.
     */
    private function serverExtensions(string $path): array
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        $found  = preg_match('#\^?/?files/[^\n]*?\\\\?\.\((?:\?i:)?([a-z0-9|?]+)\)#i', $source, $matches);

        $this->assertSame(1, $found, "у {$path} не знайдено переліку розширень для /files/");

        $extensions = [];
        foreach (explode('|', strtolower($matches[1])) as $alternative) {
            $extensions[] = str_replace('?', '', $alternative);
            if (str_contains($alternative, '?')) {
                $extensions[] = preg_replace('/.\?/', '', $alternative);
            }
        }

        return array_unique($extensions);
    }
}
