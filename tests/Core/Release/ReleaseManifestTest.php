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

    public function testGenerationAndRequiresFromDefaultToTheLegacyPackage(): void
    {
        $manifest = new ReleaseManifest($this->fixturesDir . '/sample-manifest.json');

        $this->assertSame(1, $manifest->packageFormat());
        $this->assertNull($manifest->requiresFrom());
    }

    /** @param array<string, mixed> $fields */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidFieldsProvider')]
    public function testInvalidGenerationOrRequiresFromIsRejected(array $fields): void
    {
        $path = tempnam(sys_get_temp_dir(), 'release-manifest-');
        file_put_contents($path, json_encode(['include' => ['Okay/Core/']] + $fields));

        try {
            $this->expectException(\RuntimeException::class);
            new ReleaseManifest($path);
        } finally {
            unlink($path);
        }
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function invalidFieldsProvider(): array
    {
        return [
            'покоління рядком'       => [['packageFormat' => '2']],
            'нульове покоління'      => [['packageFormat' => 0]],
            'покоління, якого ядро не знає' => [['packageFormat' => \Okay\Core\Update\PackageFormat::SUPPORTED + 1]],
            'requiresFrom без патча' => [['requiresFrom' => '1.3']],
            'requiresFrom з v'       => [['requiresFrom' => 'v1.3.12']],
        ];
    }
}
