<?php

namespace Admin;

use Okay\Admin\Controllers\ThemeAdmin;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Копія теми не має вдавати успіх. Одразу після dirCopy() контролер безумовно
 * перемикав вітрину на нову тему - тобто провал копіювання відправляв магазин
 * на каталог, якого немає, а адмінка при цьому не показувала нічого.
 */
class ThemeAdminDirCopyTest extends TestCase
{
    private array $cleanup = [];

    protected function tearDown(): void
    {
        foreach ($this->cleanup as $path) {
            $this->removeRecursively($path);
        }
        $this->cleanup = [];
    }

    public function testCopyReportsSuccessAndCopiesTheTree()
    {
        $src = $this->tempPath('src');
        mkdir($src . '/html', 0755, true);
        file_put_contents($src . '/html/index.tpl', 'template');
        $dst = $this->tempPath('dst');

        $this->assertTrue($this->dirCopy($src, $dst));
        $this->assertFileExists($dst . '/html/index.tpl');
        $this->assertSame('template', file_get_contents($dst . '/html/index.tpl'));
    }

    /**
     * Батько-файл, а не права: тести можуть іти від root, який права обходить.
     *
     * Каталог-джерело порожній навмисно: з файлами всередині провал помітив би й
     * copy(), і перевірка mkdir лишилась би непокритою.
     */
    public function testCopyReportsFailureWhenDestinationCannotBeCreated()
    {
        $src = $this->tempPath('src');
        mkdir($src, 0755, true);

        $blocker = $this->tempPath('blocker');
        file_put_contents($blocker, 'not a directory');

        $this->assertFalse($this->dirCopy($src, $blocker . '/theme'));
    }

    public function testFailureDeeperInTheTreeIsReported()
    {
        $src = $this->tempPath('src');
        mkdir($src . '/html', 0755, true);
        file_put_contents($src . '/html/index.tpl', 'template');

        $dst = $this->tempPath('dst');
        mkdir($dst, 0755, true);
        // На місці підкаталогу, який має з'явитись, лежить файл
        file_put_contents($dst . '/html', 'not a directory');

        $this->assertFalse($this->dirCopy($src, $dst));
    }

    private function dirCopy(string $src, string $dst): bool
    {
        $controller = (new ReflectionClass(ThemeAdmin::class))->newInstanceWithoutConstructor();
        $method = (new ReflectionClass(ThemeAdmin::class))->getMethod('dirCopy');

        return $method->invokeArgs($controller, [$src, $dst]);
    }

    private function tempPath(string $suffix): string
    {
        $path = sys_get_temp_dir() . '/okaycms-theme-' . $suffix . '-' . getmypid() . '-' . uniqid();
        $this->cleanup[] = $path;

        return $path;
    }

    private function removeRecursively(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);

            return;
        }

        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($path);
    }
}
