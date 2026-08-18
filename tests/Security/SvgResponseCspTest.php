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
    public function testSvgResponsesCarryTheSandboxPolicy()
    {
        $headers = (new ImageSvg())->getSpecialHeaders();

        $this->assertContains("Content-Security-Policy: default-src 'none'; sandbox", $headers);
        $this->assertContains('Content-type: image/svg+xml', $headers);
    }
}
