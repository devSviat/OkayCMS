<?php

namespace Okay\Core\Security\Filemanager;

/**
 * Приводить шлях із запиту до абсолютного шляху всередині дозволеного кореня.
 *
 * Повертає null для traversal, абсолютних шляхів, схем і NUL-байтів —
 * код, що викликає, зобов'язаний вважати null відмовою, а не "шлях не знайдено".
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
     * Перевірка значення із запиту без звертання до файлової системи.
     *
     * Потрібна як єдиний відсів на вході процедурних точок файлового
     * менеджера: там шлях склеюється з конфігом більш ніж у двадцяти
     * місцях, і переписувати кожне небезпечніше, ніж відхилити запит цілком.
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

        // Будь-який сегмент ".." виводить за межі кореня.
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

        // Схеми (http://, php://, data:) та абсолютні шляхи неприпустимі.
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

        // realpath() розкриває симлінки, тому перевірка нижче відсікає і
        // симлінк усередині upload-кореня, що вказує назовні.
        if ($resolved !== $this->root && strpos($resolved, $this->root . '/') !== 0) {
            return null;
        }

        return $resolved;
    }
}
