<?php


namespace Okay\Admin\Helpers;


use Okay\Core\Config;
use Okay\Core\Image;
use Okay\Core\Modules\Extender\ExtenderFacade;
use Okay\Core\Request;
use Okay\Core\Security\Filemanager\PathResolver;
use Okay\Core\Security\SafeFileName;
use Okay\Core\Security\SvgSanitizer;

/**
 * Вміст files/uploads для вибирача файлів редактора: список, завантаження, видалення.
 *
 * Білі списки розширень мусять лишатись підмножиною того, що сервер віддає з /files/
 * (`docs/nginx/nginx.conf`, `.htaccess`). Дозволити тут ширше означає прийняти файл,
 * якого браузер потім не побачить.
 */
class BackendFilePickerHelper
{
    private const IMAGE_EXTENSIONS    = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'ico'];
    private const MEDIA_EXTENSIONS    = ['mp3', 'mp4', 'ogg', 'webm'];
    private const DOCUMENT_EXTENSIONS = ['pdf', 'zip', 'xls', 'xlsx', 'doc', 'docx', 'csv'];

    private const UPLOAD_DIR = 'files/uploads/';

    private $config;
    private $image;
    private $request;

    public function __construct(Config $config, Image $image, Request $request)
    {
        $this->config  = $config;
        $this->image   = $image;
        $this->request = $request;
    }

    /**
     * @param string $path   тека всередині files/uploads
     * @param string $type   filetype з TinyMCE: image, media або file
     * @param string $query  пошук за іменем
     * @param int    $page
     * @param int    $perPage
     * @return array
     */
    public function findFiles($path, $type, $query, $page, $perPage)
    {
        $result = [
            'folders'    => [],
            'files'      => [],
            'page'       => 1,
            'pagesCount' => 1,
            'total'      => 0,
        ];

        if (($directory = $this->resolve($path)) === null) {
            return ExtenderFacade::execute(__METHOD__, $result, func_get_args());
        }

        $extensions = $this->extensionsFor($type);
        $entries    = @scandir($directory) ?: [];
        $files      = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $absolute = $directory . '/' . $entry;

            if (is_dir($absolute)) {
                $result['folders'][] = ['name' => $entry, 'path' => $this->join($path, $entry)];
                continue;
            }

            $extension = strtolower((string)pathinfo($entry, PATHINFO_EXTENSION));
            if (!in_array($extension, $extensions, true)) {
                continue;
            }

            if ($query !== '' && mb_stripos($entry, $query) === false) {
                continue;
            }

            $files[] = [
                'name'      => $entry,
                'extension' => $extension,
                'size'      => (int)@filesize($absolute),
                'modified'  => (int)@filemtime($absolute),
                'isImage'   => in_array($extension, self::IMAGE_EXTENSIONS, true),
                'path'      => $this->join($path, $entry),
                'url'       => $this->url($path, $entry),
            ];
        }

        usort($files, function ($a, $b) {
            return $b['modified'] <=> $a['modified'];
        });

        usort($result['folders'], function ($a, $b) {
            return strnatcasecmp($a['name'], $b['name']);
        });

        $perPage = max(1, (int)$perPage);
        $total   = count($files);
        $pages   = max(1, (int)ceil($total / $perPage));
        $page    = min($pages, max(1, (int)$page));

        $result['files']      = array_slice($files, ($page - 1) * $perPage, $perPage);
        $result['page']       = $page;
        $result['pagesCount'] = $pages;
        $result['total']      = $total;

        return ExtenderFacade::execute(__METHOD__, $result, func_get_args());
    }

    /**
     * @param array  $file запис із $_FILES
     * @param string $path тека всередині files/uploads
     * @return array{name: string, url: string}|false
     */
    public function uploadFile($file, $path = '')
    {
        $result = false;

        if (empty($file['name']) || empty($file['tmp_name']) || !empty($file['error'])) {
            return ExtenderFacade::execute(__METHOD__, $result, func_get_args());
        }

        if (($directory = $this->resolve($path)) === null) {
            return ExtenderFacade::execute(__METHOD__, $result, func_get_args());
        }

        $name = $this->image->correctFilename(SafeFileName::basename($file['name']));
        $name = pathinfo($name, PATHINFO_BASENAME);

        $extension = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
        if ($name === '' || !in_array($extension, $this->extensionsFor('file'), true)) {
            return ExtenderFacade::execute(__METHOD__, $result, func_get_args());
        }

        $name = $this->uniqueName($directory, $name);

        if (!$this->moveUploadedFile($file['tmp_name'], $directory . '/' . $name)) {
            return ExtenderFacade::execute(__METHOD__, $result, func_get_args());
        }

        // SVG браузер виконує як документ, тому переписуємо його за білим списком
        // до того, як файл стане доступним за URL.
        if ($extension === 'svg' && !(new SvgSanitizer())->sanitizeFile($directory . '/' . $name)) {
            @unlink($directory . '/' . $name);
            return ExtenderFacade::execute(__METHOD__, $result, func_get_args());
        }

        $result = ['name' => $name, 'url' => $this->url($path, $name)];

        return ExtenderFacade::execute(__METHOD__, $result, func_get_args());
    }

    /**
     * @param string $path тека всередині files/uploads
     * @param string $name
     * @return bool
     */
    public function deleteFile($path, $name)
    {
        $result = false;
        $name   = SafeFileName::basename($name);

        if ($name === '' || ($directory = $this->resolve($path)) === null) {
            return ExtenderFacade::execute(__METHOD__, $result, func_get_args());
        }

        $target = $directory . '/' . $name;
        if (is_file($target)) {
            $result = (bool)@unlink($target);
        }

        return ExtenderFacade::execute(__METHOD__, $result, func_get_args());
    }

    /**
     * Тека на рівень вище або null, якщо ми в корені.
     *
     * @param string $path
     * @return string|null
     */
    public function parentPath($path)
    {
        $path = trim((string)$path, '/');

        if ($path === '') {
            return null;
        }

        $parent = trim((string)dirname($path), '/');

        return ExtenderFacade::execute(__METHOD__, $parent === '.' ? '' : $parent, func_get_args());
    }

    /**
     * Окремим методом, бо move_uploaded_file приймає лише справжнє
     * завантаження і поза HTTP-запитом не спрацює.
     *
     * @param string $from
     * @param string $to
     * @return bool
     */
    protected function moveUploadedFile($from, $to)
    {
        return @move_uploaded_file($from, $to);
    }

    private function extensionsFor($type)
    {
        switch ($type) {
            case 'image':
                return self::IMAGE_EXTENSIONS;
            case 'media':
                return self::MEDIA_EXTENSIONS;
            default:
                return array_merge(self::IMAGE_EXTENSIONS, self::MEDIA_EXTENSIONS, self::DOCUMENT_EXTENSIONS);
        }
    }

    private function resolve($path)
    {
        $root = rtrim($this->config->root_dir, '/') . '/' . self::UPLOAD_DIR;

        if (!is_dir($root)) {
            return null;
        }

        return (new PathResolver($root))->resolve(trim((string)$path, '/'));
    }

    private function uniqueName($directory, $name)
    {
        $base      = pathinfo($name, PATHINFO_FILENAME);
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $candidate = $name;
        $index     = 0;

        while (file_exists($directory . '/' . $candidate)) {
            $candidate = $base . '_' . ++$index . ($extension === '' ? '' : '.' . $extension);
        }

        return $candidate;
    }

    private function join($path, $name)
    {
        $path = trim((string)$path, '/');

        return $path === '' ? $name : $path . '/' . $name;
    }

    private function url($path, $name)
    {
        return $this->request->getRootUrl() . '/' . self::UPLOAD_DIR . $this->join($path, $name);
    }
}
