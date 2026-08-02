<?php

namespace Security;

use Okay\Core\Security\SafeFileName;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class SafeFileNameTest extends TestCase
{
    #[DataProvider('traversalProvider')]
    public function testTraversalIsStrippedToASingleSegment($input, $expected)
    {
        $this->assertSame($expected, SafeFileName::basename($input));
    }

    public static function traversalProvider()
    {
        return [
            // Саме ця форма пробивала trim($name, '.'): крайніх крапок немає,
            // тому старий фільтр пропускав шлях повністю.
            'nested traversal'   => ['a/../../../config/config.php', 'config.php'],
            'leading dot slash'  => ['../shell.png', 'shell.png'],
            'deep traversal'     => ['../../../../etc/passwd', 'passwd'],
            'absolute path'      => ['/etc/passwd', 'passwd'],
            'backslash'          => ['a\\..\\..\\config.php', 'config.php'],
            'mixed separators'   => ['a\\../b/../c.png', 'c.png'],
            'plain name'         => ['logo.png', 'logo.png'],
            'dots inside name'   => ['my.photo.2026.jpg', 'my.photo.2026.jpg'],
            'trailing dot'       => ['evil.php.', 'evil.php'],
        ];
    }

    #[DataProvider('rejectedProvider')]
    public function testUnusableValuesBecomeEmptyString($input)
    {
        $this->assertSame('', SafeFileName::basename($input));
    }

    public static function rejectedProvider()
    {
        return [
            'nul byte'    => ["logo.png\0.php"],
            'only dots'   => ['..'],
            'single dot'  => ['.'],
            'empty'       => [''],
            'null'        => [null],
            'array'       => [['x']],
            'int'         => [42],
            'root slash'  => ['/'],
        ];
    }

    #[DataProvider('themeNameProvider')]
    public function testThemeNameKeepsOnlyASingleSafeSegment($input, $expected)
    {
        $this->assertSame($expected, SafeFileName::themeName($input));
    }

    public static function themeNameProvider()
    {
        return [
            // dirDelete('design/' . '../..') рекурсивно стирало каталоги поза design/.
            'traversal'      => ['../..', ''],
            'nested'         => ['okay_shop/../../..', 'okay_shop'],
            'slash'          => ['a/b', 'ab'],
            'nul byte'       => ["okay\0", 'okay'],
            'valid'          => ['okay_shop_v2', 'okay_shop_v2'],
            'valid dashed'   => ['my-theme-1', 'my-theme-1'],
            'null'           => [null, ''],
            'array'          => [['x'], ''],
        ];
    }
}
