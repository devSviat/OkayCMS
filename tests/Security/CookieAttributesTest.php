<?php

namespace Security;

use PHPUnit\Framework\TestCase;

class CookieAttributesTest extends TestCase
{
    /**
     * @dataProvider cookieFileProvider
     */
    public function testEverySetcookieUsesTheOptionsArrayForm($file)
    {
        $source = $this->read($file);

        preg_match_all('/setcookie\s*\(/', $source, $matches, PREG_OFFSET_CAPTURE);
        $this->assertNotEmpty($matches[0], $file . ': no setcookie found, provider is stale');

        foreach ($matches[0] as $match) {
            // Вікно в байтах: кириличні коментарі всередині виклику
            // займають по два байти на символ.
            $tail = substr($source, $match[1], 1200);

            $this->assertStringContainsString('samesite', $tail, $file);
            $this->assertStringContainsString('httponly', $tail, $file);
            $this->assertStringContainsString('secure', $tail, $file);
        }
    }

    /**
     * Жодну з цих кук не читає JavaScript, тому всі вони httponly.
     * Виняток лише okay_csrf — він має власний тест.
     *
     * @dataProvider cookieFileProvider
     */
    public function testStorefrontAndAdminCookiesAreHttpOnly($file)
    {
        $source = $this->read($file);

        $this->assertStringNotContainsString("'httponly' => false", $source, $file);
    }

    public function cookieFileProvider()
    {
        return [
            'browsed products' => ['Okay/Core/BrowsedProducts.php'],
            'comparison'       => ['Okay/Core/Comparison.php'],
            'wishlist'         => ['Okay/Core/WishList.php'],
            'cart'             => ['Okay/Core/Cart.php'],
            'user referer'     => ['Okay/Core/UserReferer/UserReferer.php'],
            'user helper'      => ['Okay/Helpers/UserHelper.php'],
            'index admin'      => ['backend/Controllers/IndexAdmin.php'],
            'filemanager'      => ['backend/design/js/filemanager/dialog.php'],
            'storefront index' => ['index.php'],
        ];
    }

    public function testNoLegacyPositionalSetcookieRemains()
    {
        $root = dirname(__DIR__, 2);
        $offenders = [];

        foreach (['Okay', 'backend'] as $dir) {
            foreach ($this->phpFiles($root . '/' . $dir) as $file) {
                $source = file_get_contents($file);
                if ($source === false || strpos($source, 'setcookie') === false) {
                    continue;
                }

                if (preg_match_all('/setcookie\s*\(/', $source, $m, PREG_OFFSET_CAPTURE)) {
                    foreach ($m[0] as $hit) {
                        $tail = substr($source, $hit[1], 1200);
                        if (strpos($tail, 'samesite') === false) {
                            $offenders[] = str_replace($root . '/', '', $file);
                            break;
                        }
                    }
                }
            }
        }

        $this->assertSame([], array_values(array_unique($offenders)));
    }

    private function phpFiles($dir)
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                yield $file->getPathname();
            }
        }
    }

    private function read($file)
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $file);
        $this->assertIsString($source, $file);

        return $source;
    }
}
