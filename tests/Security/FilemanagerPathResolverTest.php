<?php

namespace Security;

use Okay\Core\Security\Filemanager\PathResolver;
use PHPUnit\Framework\TestCase;

class FilemanagerPathResolverTest extends TestCase
{
    private $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir() . '/okay-path-resolver-' . getmypid();
        @mkdir($this->root . '/nested/deep', 0777, true);
        file_put_contents($this->root . '/nested/deep/file.txt', 'x');
    }

    protected function tearDown(): void
    {
        @unlink($this->root . '/nested/deep/file.txt');
        @rmdir($this->root . '/nested/deep');
        @rmdir($this->root . '/nested');
        @rmdir($this->root);

        parent::tearDown();
    }

    public function testResolvesPathsInsideTheRoot()
    {
        $resolver = new PathResolver($this->root);

        $this->assertSame(realpath($this->root . '/nested/deep/file.txt'), $resolver->resolve('nested/deep/file.txt'));
        $this->assertSame(realpath($this->root . '/nested'), $resolver->resolve('nested'));
        $this->assertSame(realpath($this->root), $resolver->resolve(''));
        $this->assertSame(realpath($this->root), $resolver->resolve('.'));
    }

    /**
     * @dataProvider rejectedPathProvider
     */
    public function testRejectsUnsafePaths($path)
    {
        $resolver = new PathResolver($this->root);

        $this->assertNull($resolver->resolve($path));
    }

    public function rejectedPathProvider()
    {
        return [
            'traversal'         => ['../../etc/passwd'],
            'nested traversal'  => ['nested/../../../etc/passwd'],
            'absolute unix'     => ['/etc/passwd'],
            'absolute windows'  => ['C:\\Windows\\win.ini'],
            'backslash'         => ['nested\\..\\..\\etc'],
            'scheme http'       => ['http://example.com/x'],
            'scheme php'        => ['php://input'],
            'scheme data'       => ['data:text/plain,x'],
            'nul byte'          => ["nested/deep/file.txt\0.png"],
            'null input'        => [null],
            'missing file'      => ['nested/deep/nope.txt'],
        ];
    }

    public function testRootIsNormalised()
    {
        $resolver = new PathResolver($this->root . '/');

        $this->assertSame(realpath($this->root), $resolver->root());
    }

    public function testMissingRootIsRejected()
    {
        $this->expectException(\InvalidArgumentException::class);

        new PathResolver($this->root . '/does-not-exist');
    }
}
