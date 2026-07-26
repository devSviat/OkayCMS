<?php

namespace Okay\Core\Security\Filemanager;

/**
 * Приводит путь из запроса к абсолютному пути внутри разрешённого корня.
 *
 * Возвращает null для traversal, абсолютных путей, схем и NUL-байтов —
 * вызывающий код обязан считать null отказом, а не "путь не найден".
 */
class PathResolver
{
    /** @var string */
    private $root;

    public function __construct($rootDir)
    {
        $resolved = realpath((string)$rootDir);

        if ($resolved === false) {
            throw new \InvalidArgumentException('Filemanager root does not exist: ' . $rootDir);
        }

        $this->root = $resolved;
    }

    public function root()
    {
        return $this->root;
    }

    public function resolve($relativePath)
    {
        if (!is_string($relativePath)) {
            return null;
        }

        if (strpos($relativePath, "\0") !== false) {
            return null;
        }

        if (strpos($relativePath, '\\') !== false) {
            return null;
        }

        // Схемы (http://, php://, data:) и абсолютные пути недопустимы.
        if (preg_match('~^[a-zA-Z][a-zA-Z0-9+.-]*:~', $relativePath)) {
            return null;
        }

        if ($relativePath !== '' && $relativePath[0] === '/') {
            return null;
        }

        $candidate = $this->root;
        if ($relativePath !== '' && $relativePath !== '.') {
            $candidate .= '/' . $relativePath;
        }

        $resolved = realpath($candidate);
        if ($resolved === false) {
            return null;
        }

        // realpath() раскрывает симлинки, поэтому проверка ниже отсекает и
        // симлинк внутри upload-корня, указывающий наружу.
        if ($resolved !== $this->root && strpos($resolved, $this->root . '/') !== 0) {
            return null;
        }

        return $resolved;
    }
}
