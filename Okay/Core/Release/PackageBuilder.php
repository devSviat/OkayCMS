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

    private function readMinPhp(string $repoPath): string
    {
        $composerJson = json_decode(file_get_contents($repoPath . '/composer.json'), true);

        return $composerJson['require']['php'] ?? throw new \RuntimeException(
            "composer.json at {$repoPath} has no 'require.php' constraint"
        );
    }
}
