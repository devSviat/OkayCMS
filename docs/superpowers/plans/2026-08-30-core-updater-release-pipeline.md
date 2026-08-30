# Core Updater — Release Pipeline (Plan A) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the GitHub-Release publishing side of the fork self-updater — a
CLI command that packages "core" files into a versioned, checksummed zip, and
a GitHub Actions workflow that runs it on `okaycms-fork/v*` tags and publishes
a GitHub Release with the package attached.

**Architecture:** A new `Okay\Core\Release` namespace holds two plain,
dependency-free PHP classes (`ReleaseManifest`, `PackageBuilder`) that do all
the file-resolution and packaging work, unit-testable against fixture
directories without booting the framework. A thin Symfony Console command
(`ok release:build-package`, following the existing `DatabaseDeployCommand`
pattern) wraps them for CLI/CI use. `.github/workflows/release.yml` invokes
that command on tag push and publishes the result via `gh release create`.

**Tech Stack:** PHP 8.4/8.5, Symfony Console (existing `Okay\Core\Console\*`
pattern), PHPUnit, GitHub Actions, `gh` CLI.

**Spec:** `docs/superpowers/specs/2026-08-30-core-self-updater-design.md`
(§1–§6, §13, §15 steps 1–2). This plan implements only the *build and publish*
side — nothing here reads a package back or applies it (that is Plan C, the
`CoreUpdater` module, written after this plan is executed and verified).

## Global Constraints

- PHP `^8.4` (repo's existing `composer.json` constraint) — no 8.5-only syntax.
- `vendor/` is never included in the package (spec §5) — the target install
  regenerates it via `composer install` at apply time, not shipped here.
- The core/user-data boundary is an explicit allow-list
  (`release-manifest.json`), never a heuristic (spec §5).
- Upstream tags (`4.x.x`) must never be touched or receive a Release wrapper —
  this plan only creates `okaycms-fork/v*` tags and releases.
- No signing in v1 — SHA-256 checksum only (spec §12).
- Code comments: Ukrainian, short, explain *why* not *what*, no narrative of
  how something was found (per project CLAUDE.md). No placeholders/TODOs.
- Every new PHP class follows the project's DI rules: constructor
  type-hints only for classes actually needing another service; a class with
  no dependencies is never wired into `services.php` needlessly (YAGNI).

---

## File Structure

- `release-manifest.json` (repo root) — human-reviewed allow-list.
- `release-migrations/pending/` (repo root, new, `.gitkeep` placeholder) —
  where a contributor drops a new core `.up.sql` file before a release; the
  build command bundles whatever is present at tag time. Consumption of
  these files (`ok_core_migrations` tracking) is Plan B/C's job, not this
  plan's.
- `Okay/Core/Release/ReleaseManifest.php` — parses `release-manifest.json`,
  resolves it to a concrete file list against a repo root.
- `Okay/Core/Release/PackageBuilder.php` — builds `version.json`,
  `manifest.json`, `payload/`, `migrations/`, zips them, writes
  `checksums.txt`.
- `Okay/Core/Console/Commands/Release/ReleaseBuildPackageCommand.php` — CLI
  wrapper, registered in `Okay/Core/Console/Application.php`.
- `.github/workflows/release.yml` — new workflow.
- Tests: `tests/Core/Release/ReleaseManifestTest.php`,
  `tests/Core/Release/PackageBuilderTest.php`,
  `tests/Core/Release/ReleaseManifestPathsExistTest.php` (guard test against
  the real repo tree),
  `tests/Core/Console/Commands/Release/ReleaseBuildPackageCommandTest.php`.
- Fixtures: `tests/Core/Release/fixtures/sample-repo/` — a tiny fake tree
  used by `ReleaseManifestTest`/`PackageBuilderTest` so tests don't walk the
  real ~5000-file repo.

---

### Task 1: `release-manifest.json` + `ReleaseManifest`

**Files:**
- Create: `release-manifest.json`
- Create: `Okay/Core/Release/ReleaseManifest.php`
- Create: `tests/Core/Release/ReleaseManifestTest.php`
- Create fixtures:
  `tests/Core/Release/fixtures/sample-repo/Okay/Core/Foo.php`
  `tests/Core/Release/fixtures/sample-repo/Okay/Core/Bar.php`
  `tests/Core/Release/fixtures/sample-repo/backend/Controller.php`
  `tests/Core/Release/fixtures/sample-repo/backend/design/theme.tpl`
  `tests/Core/Release/fixtures/sample-manifest.json`

**Interfaces:**
- Produces: `Okay\Core\Release\ReleaseManifest::__construct(string $manifestPath)`,
  `ReleaseManifest::resolveFiles(string $basePath): array` (returns a sorted
  `string[]` of repo-relative paths, no duplicates).

- [ ] **Step 1: Write the failing test**

Create the fixture tree first (plain content, doesn't matter what's inside —
these files exist only to be discovered):

```php
// tests/Core/Release/fixtures/sample-repo/Okay/Core/Foo.php
<?php
// fixture file for ReleaseManifestTest
```

```php
// tests/Core/Release/fixtures/sample-repo/Okay/Core/Bar.php
<?php
// fixture file for ReleaseManifestTest
```

```php
// tests/Core/Release/fixtures/sample-repo/backend/Controller.php
<?php
// fixture file for ReleaseManifestTest
```

```
// tests/Core/Release/fixtures/sample-repo/backend/design/theme.tpl
{* fixture file, must be excluded by the manifest *}
```

```json
// tests/Core/Release/fixtures/sample-manifest.json
{
    "include": [
        "Okay/Core/",
        "backend/"
    ],
    "exclude": [
        "backend/design/"
    ]
}
```

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose -f /home/sviat/projects/broken/docker-compose.yml exec php-fpm sh -c "cd /path/to/okaycms-checkout && vendor/bin/phpunit tests/Core/Release/ReleaseManifestTest.php"`

(This plan targets the fork repo, not `broken` — run PHPUnit however this
repo's `make test`-equivalent is invoked locally; if no container wraps the
fork repo yet, run `vendor/bin/phpunit tests/Core/Release/ReleaseManifestTest.php`
directly with the PHP 8.4/8.5 binary used for this repo.)

Expected: FAIL — `Class "Okay\Core\Release\ReleaseManifest" not found`.

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace Okay\Core\Release;

class ReleaseManifest
{
    /** @var string[] */
    private array $include;

    /** @var string[] */
    private array $exclude;

    public function __construct(string $manifestPath)
    {
        if (!file_exists($manifestPath)) {
            throw new \RuntimeException("Release manifest not found: {$manifestPath}");
        }

        $data = json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);

        $this->include = $data['include'] ?? [];
        $this->exclude = $data['exclude'] ?? [];

        if (empty($this->include)) {
            throw new \RuntimeException("Release manifest has an empty 'include' list: {$manifestPath}");
        }
    }

    /** @return string[] repo-relative paths, sorted, deduplicated */
    public function resolveFiles(string $basePath): array
    {
        $basePath = rtrim($basePath, '/');
        $files = [];

        foreach ($this->include as $includePath) {
            $fullPath = $basePath . '/' . $includePath;

            if (is_file($fullPath)) {
                $files[$includePath] = true;
                continue;
            }

            if (!is_dir($fullPath)) {
                throw new \RuntimeException("Release manifest include path does not exist: {$includePath}");
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($fullPath, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }

                $relative = ltrim(substr($file->getPathname(), strlen($basePath)), '/');

                if ($this->isExcluded($relative)) {
                    continue;
                }

                $files[$relative] = true;
            }
        }

        $relativePaths = array_keys($files);
        sort($relativePaths);

        return $relativePaths;
    }

    private function isExcluded(string $relativePath): bool
    {
        foreach ($this->exclude as $excludePath) {
            $prefix = rtrim($excludePath, '/') . '/';
            if ($relativePath === $excludePath || str_starts_with($relativePath, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Same command as Step 2. Expected: PASS (3 tests, 3 assertions or more).

- [ ] **Step 5: Add the real `release-manifest.json` at repo root**

```json
{
    "include": [
        "Okay/Core/",
        "Okay/Controllers/",
        "Okay/Entities/",
        "Okay/Helpers/",
        "Okay/Requests/",
        "Okay/Extenders/",
        "backend/",
        "Okay/Modules/OkayCMS/",
        "composer.json",
        "composer.lock",
        "ok",
        "index.php",
        "1DB_changes/"
    ],
    "exclude": [
        "backend/design/"
    ]
}
```

This is the actual allow-list from spec §5 — review it against the current
repo tree before committing (paths must match exactly what exists; Task 6
below adds a guard test that fails loudly if they drift).

- [ ] **Step 6: Commit**

```bash
git add release-manifest.json Okay/Core/Release/ReleaseManifest.php \
  tests/Core/Release/ReleaseManifestTest.php tests/Core/Release/fixtures
git commit -m "feat(release): парсинг release-manifest.json і резолв core-файлів"
```

---

### Task 2: `PackageBuilder` — payload, `version.json`, `manifest.json`

**Files:**
- Create: `Okay/Core/Release/PackageBuilder.php`
- Create: `tests/Core/Release/PackageBuilderTest.php`

**Interfaces:**
- Consumes: `ReleaseManifest::__construct(string $manifestPath)`,
  `ReleaseManifest::resolveFiles(string $basePath): array` (Task 1).
- Produces: `Okay\Core\Release\PackageBuilder::stage(string $repoPath, string
  $manifestPath, string $forkVersion, string $upstreamBase, string
  $stagingDir, ?string $migrationsPath = null): array` — returns
  `['fileCount' => int, 'migrationsCount' => int, 'requiresMigrations' =>
  bool]`. After calling it, `$stagingDir` contains `payload/`, `migrations/`,
  `version.json`, `manifest.json` — consumed by Task 3's zip step.

`minPhp` is read internally from `{$repoPath}/composer.json`'s
`require.php` — callers never pass it explicitly (single source of truth,
no drift between the package metadata and the actual composer constraint).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Core\Release;

use Okay\Core\Release\PackageBuilder;
use PHPUnit\Framework\TestCase;

class PackageBuilderTest extends TestCase
{
    private string $fixturesDir;
    private string $stagingDir;

    protected function setUp(): void
    {
        $this->fixturesDir = __DIR__ . '/fixtures';
        $this->stagingDir = sys_get_temp_dir() . '/package-builder-test-' . uniqid();
        mkdir($this->stagingDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->stagingDir);
    }

    public function testStageCopiesIncludedFilesAndWritesMetadata(): void
    {
        $builder = new PackageBuilder();

        $result = $builder->stage(
            $this->fixturesDir . '/sample-repo',
            $this->fixturesDir . '/sample-manifest.json',
            '1.1.0',
            '4.6.0',
            $this->stagingDir
        );

        $this->assertSame(3, $result['fileCount']);
        $this->assertSame(0, $result['migrationsCount']);
        $this->assertFalse($result['requiresMigrations']);

        $this->assertFileExists($this->stagingDir . '/payload/Okay/Core/Foo.php');
        $this->assertFileExists($this->stagingDir . '/payload/Okay/Core/Bar.php');
        $this->assertFileExists($this->stagingDir . '/payload/backend/Controller.php');
        $this->assertFileDoesNotExist($this->stagingDir . '/payload/backend/design/theme.tpl');

        $version = json_decode(file_get_contents($this->stagingDir . '/version.json'), true);
        $this->assertSame('1.1.0', $version['forkVersion']);
        $this->assertSame('4.6.0', $version['upstreamBase']);
        $this->assertSame('^8.4', $version['minPhp']);
        $this->assertFalse($version['requiresMigrations']);
        $this->assertNotEmpty($version['releasedAt']);

        $manifest = json_decode(file_get_contents($this->stagingDir . '/manifest.json'), true);
        $this->assertArrayHasKey('Okay/Core/Foo.php', $manifest['files']);
        $this->assertSame(
            hash_file('sha256', $this->fixturesDir . '/sample-repo/Okay/Core/Foo.php'),
            $manifest['files']['Okay/Core/Foo.php']
        );
        $this->assertArrayNotHasKey('backend/design/theme.tpl', $manifest['files']);
    }

    public function testStageBundlesPendingMigrationsWhenPresent(): void
    {
        $migrationsSource = $this->stagingDir . '/../pending-migrations-' . uniqid();
        mkdir($migrationsSource, 0777, true);
        file_put_contents($migrationsSource . '/1.1.0_add_column.up.sql', 'ALTER TABLE ok_foo ADD COLUMN bar INT;');

        $builder = new PackageBuilder();

        try {
            $result = $builder->stage(
                $this->fixturesDir . '/sample-repo',
                $this->fixturesDir . '/sample-manifest.json',
                '1.1.0',
                '4.6.0',
                $this->stagingDir,
                $migrationsSource
            );

            $this->assertSame(1, $result['migrationsCount']);
            $this->assertTrue($result['requiresMigrations']);
            $this->assertFileExists($this->stagingDir . '/migrations/1.1.0_add_column.up.sql');
        } finally {
            $this->removeDirectory($migrationsSource);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($dir);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Expected: FAIL — `Class "Okay\Core\Release\PackageBuilder" not found`.

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace Okay\Core\Release;

class PackageBuilder
{
    /** @return array{fileCount: int, migrationsCount: int, requiresMigrations: bool} */
    public function stage(
        string $repoPath,
        string $manifestPath,
        string $forkVersion,
        string $upstreamBase,
        string $stagingDir,
        ?string $migrationsPath = null
    ): array {
        $repoPath = rtrim($repoPath, '/');
        $manifest = new ReleaseManifest($manifestPath);
        $files = $manifest->resolveFiles($repoPath);

        $payloadDir = $stagingDir . '/payload';
        $checksums = [];

        foreach ($files as $relativePath) {
            $sourcePath = $repoPath . '/' . $relativePath;
            $targetPath = $payloadDir . '/' . $relativePath;

            if (!is_dir(dirname($targetPath))) {
                mkdir(dirname($targetPath), 0777, true);
            }

            copy($sourcePath, $targetPath);
            $checksums[$relativePath] = hash_file('sha256', $sourcePath);
        }

        $migrationsCount = $this->copyMigrations($migrationsPath, $stagingDir . '/migrations');
        $requiresMigrations = $migrationsCount > 0;

        $minPhp = $this->readMinPhp($repoPath);

        $version = [
            'forkVersion' => $forkVersion,
            'upstreamBase' => $upstreamBase,
            'minPhp' => $minPhp,
            'releasedAt' => gmdate('c'),
            'requiresMigrations' => $requiresMigrations,
        ];

        file_put_contents($stagingDir . '/version.json', json_encode($version, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        file_put_contents(
            $stagingDir . '/manifest.json',
            json_encode(['files' => $checksums], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        return [
            'fileCount' => count($files),
            'migrationsCount' => $migrationsCount,
            'requiresMigrations' => $requiresMigrations,
        ];
    }

    private function copyMigrations(?string $migrationsPath, string $targetDir): int
    {
        mkdir($targetDir, 0777, true);

        if ($migrationsPath === null || !is_dir($migrationsPath)) {
            return 0;
        }

        $count = 0;
        foreach (glob($migrationsPath . '/*.up.sql') as $migrationFile) {
            copy($migrationFile, $targetDir . '/' . basename($migrationFile));
            $count++;
        }

        return $count;
    }

    private function readMinPhp(string $repoPath): string
    {
        $composerJson = json_decode(file_get_contents($repoPath . '/composer.json'), true);

        return $composerJson['require']['php'] ?? throw new \RuntimeException(
            "composer.json at {$repoPath} has no 'require.php' constraint"
        );
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add Okay/Core/Release/PackageBuilder.php tests/Core/Release/PackageBuilderTest.php
git commit -m "feat(release): збирання payload/version.json/manifest.json у staging"
```

---

### Task 3: Zip assembly + `checksums.txt`

**Files:**
- Modify: `Okay/Core/Release/PackageBuilder.php`
- Modify: `tests/Core/Release/PackageBuilderTest.php`

**Interfaces:**
- Consumes: `PackageBuilder::stage(...)` (Task 2) — called internally by the
  new method before zipping.
- Produces: `PackageBuilder::build(string $repoPath, string $manifestPath,
  string $forkVersion, string $upstreamBase, string $outputDir, ?string
  $migrationsPath = null): array` — returns `['zipPath' => string,
  'versionJsonPath' => string, 'checksumsPath' => string, 'fileCount' =>
  int, 'migrationsCount' => int]`. This is the method Task 4's command
  actually calls; `stage()` stays available for the fixture-driven test from
  Task 2 and becomes a private implementation detail called from `build()`.

- [ ] **Step 1: Write the failing test**

Add to `PackageBuilderTest`:

```php
    public function testBuildProducesZipVersionJsonAndChecksums(): void
    {
        $outputDir = sys_get_temp_dir() . '/package-builder-output-' . uniqid();
        mkdir($outputDir, 0777, true);

        $builder = new PackageBuilder();

        try {
            $result = $builder->build(
                $this->fixturesDir . '/sample-repo',
                $this->fixturesDir . '/sample-manifest.json',
                '1.1.0',
                '4.6.0',
                $outputDir
            );

            $this->assertSame($outputDir . '/okaycms-fork-v1.1.0.zip', $result['zipPath']);
            $this->assertFileExists($result['zipPath']);
            $this->assertFileExists($result['versionJsonPath']);
            $this->assertFileExists($result['checksumsPath']);

            $zip = new \ZipArchive();
            $zip->open($result['zipPath']);
            $this->assertNotFalse($zip->locateName('version.json'));
            $this->assertNotFalse($zip->locateName('manifest.json'));
            $this->assertNotFalse($zip->locateName('payload/Okay/Core/Foo.php'));
            $this->assertFalse($zip->locateName('payload/backend/design/theme.tpl'));
            $zip->close();

            $checksums = file_get_contents($result['checksumsPath']);
            $expectedZipHash = hash_file('sha256', $result['zipPath']);
            $expectedVersionHash = hash_file('sha256', $result['versionJsonPath']);
            $this->assertStringContainsString("{$expectedZipHash}  okaycms-fork-v1.1.0.zip", $checksums);
            $this->assertStringContainsString("{$expectedVersionHash}  version.json", $checksums);
        } finally {
            $this->removeDirectory($outputDir);
        }
    }
```

- [ ] **Step 2: Run test to verify it fails**

Expected: FAIL — `Call to undefined method Okay\Core\Release\PackageBuilder::build()`.

- [ ] **Step 3: Write minimal implementation**

Add to `PackageBuilder`:

```php
    /** @return array{zipPath: string, versionJsonPath: string, checksumsPath: string, fileCount: int, migrationsCount: int} */
    public function build(
        string $repoPath,
        string $manifestPath,
        string $forkVersion,
        string $upstreamBase,
        string $outputDir,
        ?string $migrationsPath = null
    ): array {
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0777, true);
        }

        $stagingDir = sys_get_temp_dir() . '/okaycms-release-staging-' . uniqid();
        mkdir($stagingDir, 0777, true);

        $staged = $this->stage($repoPath, $manifestPath, $forkVersion, $upstreamBase, $stagingDir, $migrationsPath);

        $zipPath = $outputDir . '/okaycms-fork-v' . $forkVersion . '.zip';
        $this->assembleZip($stagingDir, $zipPath);

        $versionJsonPath = $outputDir . '/version.json';
        copy($stagingDir . '/version.json', $versionJsonPath);

        $checksumsPath = $outputDir . '/checksums.txt';
        $checksums = sprintf(
            "%s  %s\n%s  %s\n",
            hash_file('sha256', $zipPath),
            basename($zipPath),
            hash_file('sha256', $versionJsonPath),
            basename($versionJsonPath)
        );
        file_put_contents($checksumsPath, $checksums);

        return [
            'zipPath' => $zipPath,
            'versionJsonPath' => $versionJsonPath,
            'checksumsPath' => $checksumsPath,
            'fileCount' => $staged['fileCount'],
            'migrationsCount' => $staged['migrationsCount'],
        ];
    }

    private function assembleZip(string $stagingDir, string $zipPath): void
    {
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $zip->addFile($stagingDir . '/version.json', 'version.json');
        $zip->addFile($stagingDir . '/manifest.json', 'manifest.json');

        foreach (['payload', 'migrations'] as $subDir) {
            $sourceDir = $stagingDir . '/' . $subDir;
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }

                $relative = $subDir . '/' . ltrim(substr($file->getPathname(), strlen($sourceDir)), '/');
                $zip->addFile($file->getPathname(), $relative);
            }
        }

        $zip->close();
    }
```

Change `stage()`'s visibility to `private` now that `build()` is the public
entry point used outside tests — but keep `stage()` covered by Task 2's
tests directly (PHPUnit can call private methods only through the public
API, so re-point Task 2's assertions at `build()`'s staging side effects if
`stage()` becomes private; simplest fix: leave `stage()` `public` since
nothing forbids it, and it stays independently useful for testing the
pre-zip state without paying for zip I/O in every test run — no need to
force a visibility change that breaks passing tests).

- [ ] **Step 4: Run test to verify it passes**

Run the whole file: `vendor/bin/phpunit tests/Core/Release/PackageBuilderTest.php`
Expected: PASS (all tests from Task 2 and Task 3).

- [ ] **Step 5: Commit**

```bash
git add Okay/Core/Release/PackageBuilder.php tests/Core/Release/PackageBuilderTest.php
git commit -m "feat(release): збірка zip і checksums.txt з staging-директорії"
```

---

### Task 4: `release-migrations/pending/` convention directory

**Files:**
- Create: `release-migrations/pending/.gitkeep`
- Create: `release-migrations/README.md`

**Interfaces:**
- Consumes: nothing new — `PackageBuilder::build()`'s existing
  `$migrationsPath` parameter (Task 2/3) already accepts any directory; this
  task only establishes *which* directory is the convention and documents
  it for contributors and for Task 5's command default.

- [ ] **Step 1: Create the directory and placeholder**

```bash
mkdir -p /home/sviat/projects/OkayCMS/release-migrations/pending
touch /home/sviat/projects/OkayCMS/release-migrations/pending/.gitkeep
```

- [ ] **Step 2: Write the convention doc**

```markdown
# release-migrations/pending/

Core-специфічні DB-міграції для наступного релізу форку. Кожен файл —
`{fork-version}_{опис}.up.sql`, ідемпотентний (`CREATE TABLE IF NOT EXISTS`,
перевірка через `INFORMATION_SCHEMA` перед `ALTER`).

Реліз-пайплайн (`ok release:build-package`, `.github/workflows/release.yml`)
бере все, що лежить тут на момент тегування, кладе в `migrations/` пакету
релізу — і **не чистить цю директорію автоматично**. Після успішного
релізу видаліть застосовані файли звідси окремим комітом (щоб наступний
реліз не переслав їх повторно).

Формат і механізм застосування на боці CMS — див.
`docs/superpowers/specs/2026-08-30-core-self-updater-design.md`, §7.
```

- [ ] **Step 3: Commit**

```bash
git add release-migrations
git commit -m "docs(release): конвенція release-migrations/pending для core-міграцій"
```

---

### Task 5: `ReleaseBuildPackageCommand`

**Files:**
- Create: `Okay/Core/Console/Commands/Release/ReleaseBuildPackageCommand.php`
- Modify: `Okay/Core/Console/Application.php`
- Create: `tests/Core/Console/Commands/Release/ReleaseBuildPackageCommandTest.php`

**Interfaces:**
- Consumes: `PackageBuilder::build(...)` (Task 3).
- Produces: CLI command `ok release:build-package --fork-version=X.Y.Z
  [--repo-path=...] [--output-dir=build/release]
  [--manifest=release-manifest.json] [--migrations=release-migrations/pending]
  [--upstream-base=X.Y.Z]`. Exit code `Command::SUCCESS` (0) /
  `Command::FAILURE` (1). This is what Task 7's `release.yml` invokes.
  `--repo-path` defaults to the command file's own repo root
  (`dirname(__DIR__, 4)` from
  `Okay/Core/Console/Commands/Release/ReleaseBuildPackageCommand.php`);
  `--upstream-base` defaults to `Config::$version` read from
  `{repo-path}/config/config.php` when the option is omitted.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Core\Console\Commands\Release;

use Okay\Core\Console\Commands\Release\ReleaseBuildPackageCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class ReleaseBuildPackageCommandTest extends TestCase
{
    public function testBuildsPackageIntoRequestedOutputDir(): void
    {
        $repoRoot = dirname(__DIR__, 4) . '/Release/fixtures/sample-repo';
        $manifest = dirname(__DIR__, 4) . '/Release/fixtures/sample-manifest.json';
        $outputDir = sys_get_temp_dir() . '/release-command-output-' . uniqid();

        $application = new Application();
        $command = new ReleaseBuildPackageCommand();
        $application->add($command);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            '--fork-version' => '1.1.0',
            '--repo-path' => $repoRoot,
            '--upstream-base' => '4.6.0',
            '--output-dir' => $outputDir,
            '--manifest' => $manifest,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($outputDir . '/okaycms-fork-v1.1.0.zip');
        $this->assertStringContainsString('okaycms-fork-v1.1.0.zip', $tester->getDisplay());

        $this->removeDirectory($outputDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($dir);
    }
}
```

Note: this test passes `--repo-path`/`--upstream-base` as CLI options
instead of relying on `handle(Config $config)` type-hint injection — see
Step 3 for why: `Command::execute()`'s `MethodDI::getMethodArguments()`
(`Okay/Core/OkayContainer/MethodDI.php:13`) resolves a type-hinted
`Config $config` parameter via `ServiceLocator::getInstance()`, which pulls
in the full DI container bootstrap (`Okay/Core/config/services.php`) — not
just `Config`'s own (cheap, file-based) constructor. That's a heavier
dependency than a unit test needs, in the same spirit as this project's
`ci-phpunit-has-no-database` constraint (avoid framework-wide bootstrap in
unit tests, even where the specific class being avoided isn't a DB
connection itself).

- [ ] **Step 2: Run test to verify it fails**

Expected: FAIL — class not found.

- [ ] **Step 3: Write minimal implementation**

Zero-argument constructor (inherited from `Command`, no override needed) —
`Application::registerCommand()` instantiates every command with
`new $commandClass()` and nothing else
(`Okay/Core/Console/Application.php`, confirmed by reading it while writing
this plan); this command must not require that to change. `repoPath` and
`upstreamBase` become CLI options instead of constructor arguments, each
with a computed default so production usage needs no flags at all while
tests can still point them at a fixture tree:

```php
<?php

namespace Okay\Core\Console\Commands\Release;

use Okay\Core\Config;
use Okay\Core\Console\Command;
use Okay\Core\Release\PackageBuilder;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'release:build-package', description: 'Builds a fork release package (zip + manifest + checksums).')]
class ReleaseBuildPackageCommand extends Command
{
    protected function configure(): void
    {
        $defaultRepoPath = dirname(__DIR__, 4);

        $this
            ->addOption('fork-version', null, InputOption::VALUE_REQUIRED, 'Fork version being released, e.g. 1.1.0')
            ->addOption('repo-path', null, InputOption::VALUE_REQUIRED, 'Repository root to package', $defaultRepoPath)
            ->addOption('output-dir', null, InputOption::VALUE_REQUIRED, 'Where to write the package', $defaultRepoPath . '/build/release')
            ->addOption('manifest', null, InputOption::VALUE_REQUIRED, 'Path to release-manifest.json', $defaultRepoPath . '/release-manifest.json')
            ->addOption('migrations', null, InputOption::VALUE_REQUIRED, 'Pending core migrations directory', $defaultRepoPath . '/release-migrations/pending')
            ->addOption('upstream-base', null, InputOption::VALUE_REQUIRED, 'Upstream OkayCMS version this release is based on (defaults to Config::$version at --repo-path)');
    }

    protected function handle(): int
    {
        $forkVersion = $this->input->getOption('fork-version');
        if (empty($forkVersion)) {
            $this->output->writeln('<error>--fork-version is required</error>');
            return Command::FAILURE;
        }

        $repoPath = $this->input->getOption('repo-path');
        $upstreamBase = $this->input->getOption('upstream-base');

        if (empty($upstreamBase)) {
            $config = new Config($repoPath . '/config/config.php', $repoPath . '/config/config.local.php');
            $upstreamBase = $config->version;
        }

        $builder = new PackageBuilder();

        $result = $builder->build(
            $repoPath,
            $this->input->getOption('manifest'),
            $forkVersion,
            $upstreamBase,
            $this->input->getOption('output-dir'),
            $this->input->getOption('migrations')
        );

        $this->output->writeln("Package built: {$result['zipPath']}");
        $this->output->writeln("Files: {$result['fileCount']}, migrations: {$result['migrationsCount']}");

        return Command::SUCCESS;
    }
}
```

`Config`'s constructor only parses `.ini` files and computes a salt hash —
no DB connection, no `ServiceLocator` — so calling `new Config(...)`
directly here (not through `handle()`'s type-hinted DI resolution) stays
cheap and side-effect-free even when `config/config.local.php` doesn't
exist (`Config::initConfig()` already guards that path with
`file_exists()`).

Register the class in `Okay/Core/Console/Application.php`'s `$commands`
array — a one-line addition, no other change to that file.

- [ ] **Step 4: Run test to verify it passes**

Expected: PASS.

- [ ] **Step 5: Run the full existing test suite**

Run: `vendor/bin/phpunit`
Expected: PASS, including the existing `tests/Core/Console/CommandNamesTest.php`
guard test (it scans `Okay/Core/Console/Commands` automatically — the new
class must carry `#[AsCommand]` and no `$defaultName`, both already true
above).

- [ ] **Step 6: Commit**

```bash
git add Okay/Core/Console/Commands/Release/ReleaseBuildPackageCommand.php \
  Okay/Core/Console/Application.php \
  tests/Core/Console/Commands/Release/ReleaseBuildPackageCommandTest.php
git commit -m "feat(release): команда ok release:build-package"
```

---

### Task 6: Guard test — `release-manifest.json` matches the real repo

**Files:**
- Create: `tests/Core/Release/ReleaseManifestPathsExistTest.php`

**Interfaces:**
- Consumes: `ReleaseManifest` (Task 1), the real `release-manifest.json` at
  repo root (Task 1, Step 5).

- [ ] **Step 1: Write the failing test**

```php
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
```

- [ ] **Step 2: Run test to verify it fails or passes correctly**

Run against the real repo. If `release-manifest.json` (Task 1, Step 5) has
any stale path, this fails with `RuntimeException` naming the exact bad
entry — fix the manifest, not the test, in that case.

Expected once the manifest is correct: PASS.

- [ ] **Step 3: Commit**

```bash
git add tests/Core/Release/ReleaseManifestPathsExistTest.php
git commit -m "test(release): захисна перевірка, що release-manifest.json не розійшовся з деревом"
```

---

### Task 7: `.github/workflows/release.yml`

**Files:**
- Create: `.github/workflows/release.yml`

**Interfaces:**
- Consumes: `ok release:build-package` (Task 5) — the workflow's only
  fork-specific step; everything else reuses `ci.yml`'s existing test job
  as a prerequisite.

- [ ] **Step 1: Read `ci.yml` and `docker-security.yml` again before writing this**

Both already exist in this repo (`.github/workflows/ci.yml`,
`.github/workflows/docker-security.yml`) — copy their PHP setup /
`composer install` steps verbatim rather than re-inventing them, so the
release workflow doesn't silently drift from how CI actually installs
dependencies. Do not guess the PHP setup action version or `composer`
invocation — read the file.

- [ ] **Step 2: Write the workflow**

```yaml
name: Release

on:
  push:
    tags:
      - 'okaycms-fork/v*'

permissions:
  contents: write

jobs:
  test:
    # Копія тестової джоби з ci.yml — той самий набір перевірок, що й для
    # звичайного push у main, тільки тепер запускається на тегованому
    # комітеті перед публікацією релізу. Синхронізувати вручну з ci.yml
    # (composite action reusable-test.yml — окрема задача, не блокує v1).
    uses: ./.github/workflows/ci.yml

  build-and-release:
    needs: test
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Extract fork version from tag
        id: version
        run: |
          TAG="${GITHUB_REF#refs/tags/okaycms-fork/v}"
          echo "version=${TAG}" >> "$GITHUB_OUTPUT"

      - name: Set up PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'

      - name: Install dependencies
        run: composer install --no-dev --optimize-autoloader --no-interaction

      - name: Build release package
        run: php ok release:build-package --fork-version="${{ steps.version.outputs.version }}" --output-dir=build/release

      - name: Create GitHub Release
        env:
          GH_TOKEN: ${{ secrets.GITHUB_TOKEN }}
        run: |
          gh release create "okaycms-fork/v${{ steps.version.outputs.version }}" \
            --title "OkayCMS Fork v${{ steps.version.outputs.version }}" \
            --generate-notes \
            "build/release/okaycms-fork-v${{ steps.version.outputs.version }}.zip" \
            "build/release/version.json" \
            "build/release/checksums.txt"
```

`needs: test` calling `ci.yml` as a reusable workflow only works if `ci.yml`
declares `on: workflow_call` — check that at implementation time; if it
doesn't, either add that trigger to `ci.yml` (small, additive change) or
inline an equivalent test step here instead of guessing which approach the
maintainers prefer.

- [ ] **Step 3: Validate YAML syntax locally**

Run: `docker run --rm -v "$PWD/.github/workflows:/workflows" mikefarah/yq eval . /workflows/release.yml`
(or any local YAML validator available) — this only catches syntax errors,
not logical ones; the real test is Task 8 below.

- [ ] **Step 4: Commit**

```bash
git add .github/workflows/release.yml
git commit -m "ci: реліз-пайплайн для okaycms-fork/v* тегів"
```

---

### Task 8: Manual/operational — repo prep and first tag

**This task is not code and is not auto-executed.** Pushing a tag, creating
a branch, and changing branch protection on a public GitHub repository are
visible-to-others, hard-to-reverse actions — per this project's own
risk-management rules, they need the user's explicit go-ahead at the moment
they happen, not blanket approval baked into a plan written days earlier.
Whoever executes this task (human or agent) must confirm with the user
immediately before each `git push` / `gh api` / `gh release` call below —
listing the commands here is documentation of *what* to run, not
authorization to run them unattended.

- [ ] **Step 1: Create the `dev` branch** (repo currently has `develop`, not `dev`)

```bash
git -C /home/sviat/projects/OkayCMS checkout main
git -C /home/sviat/projects/OkayCMS pull origin main
git -C /home/sviat/projects/OkayCMS checkout -b dev
git -C /home/sviat/projects/OkayCMS push -u origin dev   # confirm with user first
```

- [ ] **Step 2: Branch protection on `main` and `dev`**

```bash
gh api repos/devSviat/OkayCMS/branches/main/protection -X PUT --input - <<'EOF'
{
  "required_status_checks": {"strict": true, "contexts": ["test"]},
  "enforce_admins": false,
  "required_pull_request_reviews": {"required_approving_review_count": 1},
  "restrictions": null
}
EOF
```

Repeat for `dev`. Confirm the exact required-check context names match what
`ci.yml`/`release.yml` actually report (job name `test`, or whatever the
workflow's job is called) before applying — a mismatched context name
silently makes the check optional. Confirm with the user before running.

- [ ] **Step 3: Merge Tasks 1–7's branch and tag the first fork release**

```bash
# after this plan's PR (Tasks 1-7) is reviewed and merged into main:
git -C /home/sviat/projects/OkayCMS checkout main
git -C /home/sviat/projects/OkayCMS pull origin main
git -C /home/sviat/projects/OkayCMS tag okaycms-fork/v1.0.0
git -C /home/sviat/projects/OkayCMS push origin okaycms-fork/v1.0.0   # confirm with user first
```

- [ ] **Step 4: Verify the release published correctly**

```bash
gh release view okaycms-fork/v1.0.0 --repo devSviat/OkayCMS
gh api repos/devSviat/OkayCMS/releases --jq '.[].tag_name'
```

Confirm: exactly one release, tag matches `^okaycms-fork/v\d+\.\d+\.\d+$`,
three assets attached (zip, `version.json`, `checksums.txt`), and — the
actual point of §2 in the spec — that `4.x.x` upstream tags do **not**
appear in this `releases` list at all.

---

## Self-Review

**Spec coverage:** §1 (fork version concept) — deferred to Plan B by design
(this plan doesn't touch `Config::$forkVersion`, only the release artifact's
`version.json.forkVersion`, which is independent). §2 (release vs. tag
filtering) — Task 7 + Task 8 Step 4. §3 (release.yml) — Task 7. §4
(unauthenticated checks) — not this plan's concern, that's the *reading*
side (Plan C). §5 (core/user-data boundary) — Task 1. §6 (package format) —
Task 2/3. §7 (migrations bundling, not application) — Task 4. §13 (repo
structural changes relevant to this plan) — Tasks 1, 4, 5, 7, 8.

**Placeholder scan:** no TBD/TODO; the two spots that say "read the file
again at implementation time" (Task 5 Application wiring, Task 7 `ci.yml`
reusability) are deliberate — they point at real, existing files whose exact
current shape must be re-verified rather than guessed from this plan's
memory of them, per the user's explicit instruction to verify each stage
against reality, not assume.

**Type/interface consistency:** `PackageBuilder::build()`'s return array
keys (`zipPath`, `versionJsonPath`, `checksumsPath`, `fileCount`,
`migrationsCount`) are used identically in Task 3's test and Task 5's
command. `ReleaseManifest::resolveFiles()`'s signature is unchanged from
Task 1 through Task 6.

**Scope check:** this plan stops at "a package exists on GitHub Releases,
correctly filtered from upstream tags." It deliberately does not implement
`Config::$forkVersion`, `ok_core_migrations`, or anything that *reads* a
package — those are separate plans (Plan B: core version + migration
tracking infra; Plan C: `CoreUpdater` module — check/download/verify/
backup/apply/rollback; Plan D: admin UI; Plan E: end-to-end test on
`broken` dev + docs), written and reviewed after this plan's Task 8 is
verified end-to-end, not before — per the user's explicit ask to research
and verify each stage rather than plan the whole thing up front on
assumptions.
