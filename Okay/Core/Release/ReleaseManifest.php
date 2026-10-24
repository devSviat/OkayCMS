<?php

namespace Okay\Core\Release;

use Okay\Core\Update\PackageFormat;

class ReleaseManifest
{
    /** @var string[] */
    private array $include;

    /** @var string[] */
    private array $exclude;

    private int $packageFormat;

    private ?string $requiresFrom;

    public function __construct(string $manifestPath)
    {
        if (!file_exists($manifestPath)) {
            throw new \RuntimeException("Release manifest not found: {$manifestPath}");
        }

        $data = json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);

        $this->include = $data['include'] ?? [];
        $this->exclude = $data['exclude'] ?? [];

        $packageFormat = $data['packageFormat'] ?? PackageFormat::LEGACY;
        if (!is_int($packageFormat) || $packageFormat < PackageFormat::LEGACY || $packageFormat > PackageFormat::SUPPORTED) {
            throw new \RuntimeException("Release manifest has an unsupported 'packageFormat': {$manifestPath}");
        }
        $this->packageFormat = $packageFormat;

        $requiresFrom = $data['requiresFrom'] ?? null;
        if ($requiresFrom !== null && (!is_string($requiresFrom) || !preg_match('/^\d+\.\d+\.\d+$/', $requiresFrom))) {
            throw new \RuntimeException("Release manifest has an invalid 'requiresFrom' (expected X.Y.Z): {$manifestPath}");
        }
        $this->requiresFrom = $requiresFrom;

        if (empty($this->include)) {
            throw new \RuntimeException("Release manifest has an empty 'include' list: {$manifestPath}");
        }
    }

    /** Покоління пакета (PackageFormat): що мусить уміти інсталяція, щоб його прийняти. */
    public function packageFormat(): int
    {
        return $this->packageFormat;
    }

    /** Найстаріша встановлена версія, з якої пакет можна ставити, або null — з будь-якої. */
    public function requiresFrom(): ?string
    {
        return $this->requiresFrom;
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
