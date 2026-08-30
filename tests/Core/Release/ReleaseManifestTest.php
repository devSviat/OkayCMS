<?php

namespace Core\Release;

use Okay\Core\Release\ReleaseManifest;
use PHPUnit\Framework\TestCase;

class ReleaseManifestTest extends TestCase
{
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = __DIR__ . '/fixtures';
    }

    public function testResolveFilesWalksIncludedDirectoriesAndSkipsExcluded(): void
    {
        $manifest = new ReleaseManifest($this->fixturesDir . '/sample-manifest.json');

        $files = $manifest->resolveFiles($this->fixturesDir . '/sample-repo');

        $this->assertSame(
            [
                'Okay/Core/Bar.php',
                'Okay/Core/Foo.php',
                'backend/Controller.php',
            ],
            $files
        );
    }

    public function testConstructorRejectsMissingManifestFile(): void
    {
        $this->expectException(\RuntimeException::class);

        new ReleaseManifest($this->fixturesDir . '/does-not-exist.json');
    }

    public function testConstructorRejectsEmptyIncludeList(): void
    {
        $emptyManifest = tempnam(sys_get_temp_dir(), 'release-manifest-');
        file_put_contents($emptyManifest, json_encode(['include' => []]));

        try {
            $this->expectException(\RuntimeException::class);
            new ReleaseManifest($emptyManifest);
        } finally {
            unlink($emptyManifest);
        }
    }
}
