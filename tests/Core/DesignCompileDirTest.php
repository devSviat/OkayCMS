<?php

namespace Core;

use Okay\Core\Design;
use Okay\Core\Modules\Module;
use Okay\Core\Modules\Modules;
use Okay\Core\TemplateConfig\FrontTemplateConfig;
use Okay\Core\TplMod\TplMod;
use PHPUnit\Framework\TestCase;
use Smarty\Smarty;

/**
 * Каталог скомпільованих шаблонів створюється рекурсивно, а неможливість його
 * створити - виняток, а не попередження в лозі.
 *
 * compiled/ немає на свіжому клоні: обидва сусідні каталоги везуть у git .keep_folder,
 * а цей ні. Без рекурсії mkdir() у такому дереві не створює нічого, повертає false,
 * і виконання йде далі з каталогом, якого немає - у планувальника це попередження
 * раз на хвилину, а на вітрині невиразна помилка Smarty про відсутній файл.
 */
class DesignCompileDirTest extends TestCase
{
    private array $cleanup = [];

    protected function tearDown(): void
    {
        foreach (array_reverse($this->cleanup) as $path) {
            is_dir($path) ? @rmdir($path) : @unlink($path);
        }
        $this->cleanup = [];
    }

    public function testCompileDirIsCreatedWhenParentIsMissing()
    {
        $rootDir = $this->tempRoot();
        mkdir($rootDir);

        $this->buildDesign($rootDir);

        $this->assertDirectoryExists($rootDir . 'compiled/okay_shop');
    }

    public function testExistingCompileDirIsLeftAlone()
    {
        $rootDir = $this->tempRoot();
        mkdir($rootDir . 'compiled/okay_shop', 0777, true);
        file_put_contents($rootDir . 'compiled/okay_shop/marker.txt', 'x');
        $this->cleanup[] = $rootDir . 'compiled/okay_shop/marker.txt';

        $this->buildDesign($rootDir);

        $this->assertFileExists($rootDir . 'compiled/okay_shop/marker.txt');
    }

    /**
     * Батько-файл, а не права: тести можуть іти від root, який права обходить,
     * і перевірка була б зелена завжди.
     */
    public function testUncreatableCompileDirThrows()
    {
        $blocker = sys_get_temp_dir() . '/okaycms-compile-blocker-' . getmypid() . '-' . uniqid();
        file_put_contents($blocker, 'not a directory');
        $this->cleanup[] = $blocker;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('~compiled.okay_shop~');

        $this->buildDesign($blocker . '/');
    }

    private function tempRoot(): string
    {
        $rootDir = sys_get_temp_dir() . '/okaycms-compile-' . getmypid() . '-' . uniqid() . '/';

        $this->cleanup[] = $rootDir;
        $this->cleanup[] = $rootDir . 'compiled';
        $this->cleanup[] = $rootDir . 'compiled/okay_shop';

        return $rootDir;
    }

    private function buildDesign(string $rootDir): Design
    {
        $frontTemplateConfig = $this->createStub(FrontTemplateConfig::class);
        $frontTemplateConfig->method('getTheme')->willReturn('okay_shop');

        return new Design(
            new Smarty(),
            $this->createStub(\Detection\MobileDetect::class),
            $frontTemplateConfig,
            $this->createStub(Module::class),
            $this->createStub(Modules::class),
            $this->createStub(TplMod::class),
            0,
            true,
            false,
            false,
            false,
            false,
            false,
            $rootDir
        );
    }
}
