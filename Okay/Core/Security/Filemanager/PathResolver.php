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

    /**
     * Проверка значения из запроса без обращения к файловой системе.
     *
     * Нужна как единый отсев на входе процедурных точек файлового
     * менеджера: там путь склеивается с конфигом больше чем в двадцати
     * местах, и переписывать каждое опаснее, чем отклонить запрос целиком.
     *
     * @param mixed $value
     * @return bool
     */
    public static function isSafeRelativePath($value)
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (!is_string($value)) {
            return false;
        }

        if (strpos($value, "\0") !== false || strpos($value, '\\') !== false) {
            return false;
        }

        // Любой сегмент ".." выводит за пределы корня.
        foreach (explode('/', $value) as $segment) {
            if ($segment === '..') {
                return false;
            }
        }

        if ($value[0] === '/') {
            return false;
        }

        if (preg_match('~^[a-zA-Z][a-zA-Z0-9+.-]*:~', $value)) {
            return false;
        }

        return true;
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
