<?php

namespace Security;

use Okay\Core\Adapters\Response\ImageSvg;
use PHPUnit\Framework\TestCase;

/**
 * files/resized/ - це маршрут, а не каталог: перший запит обслуговує PHP і
 * лише потім файл лежить на диску під захистом сервера. Image::resize()
 * копіює SVG наскрізь, тож без цієї CSP перше відкриття завантаженого файлу
 * виконує вкладений <script> на домені магазину.
 */
class SvgResponseCspTest extends TestCase
{
    /**
     * Apache-хостинги обслуговує .htaccess, і там FilesMatch регістрозалежний,
     * на відміну від сусідніх RewriteCond із [NC]. Без (?i) файл, збережений
     * як .SVG, віддавався без політики - перевірено живим Apache.
     */
    public function testHtaccessMatchesSvgInAnyCase()
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/.htaccess');

        $this->assertMatchesRegularExpression(
            '~<FilesMatch "\(\?i\)\\\.svg\$">~',
            $source,
            'правило CSP для svg мусить бути регістронезалежним'
        );
    }

    public function testSvgResponsesCarryTheSandboxPolicy()
    {
        $headers = (new ImageSvg())->getSpecialHeaders();

        $this->assertContains("Content-Security-Policy: default-src 'none'; sandbox", $headers);
        $this->assertContains('Content-type: image/svg+xml', $headers);
    }
}
