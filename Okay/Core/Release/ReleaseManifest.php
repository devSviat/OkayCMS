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
