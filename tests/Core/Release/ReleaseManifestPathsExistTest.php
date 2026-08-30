<?php

namespace Core\Release;

use Okay\Core\Release\ReleaseManifest;
use PHPUnit\Framework\TestCase;

/**
 * release-manifest.json — це список, який рецензується руками, не
 * генерується. Типова помилка тут — перейменований чи видалений шлях, який
 * ніхто не оновив у маніфесті: пакет релізу тихо стає неповним. Ця
 * перевірка ловить це на CI, а не на першому реальному оновленні клієнта.
 */
class ReleaseManifestPathsExistTest extends TestCase
{
    public function testEveryIncludedPathExistsInTheRepo(): void
    {
        $repoRoot = dirname(__DIR__, 3);
        $manifest = new ReleaseManifest($repoRoot . '/release-manifest.json');

        // resolveFiles() лишень зауважить порожній список у крайньому
        // випадку, а на неіснуючий include-шлях кине RuntimeException -
        // сам виклик і є перевіркою.
        $files = $manifest->resolveFiles($repoRoot);

        $this->assertNotEmpty($files, 'release-manifest.json resolved to zero files');
    }
}
