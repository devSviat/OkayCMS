<?php

namespace Okay\Core\Security;

/**
 * Белый список того, что вообще можно скачать через backend/files/index.php,
 * и права, которые для этого нужны.
 *
 * Набор совпадает с rewrite-правилами nginx (см. docs/nginx/nginx.conf):
 * админка ходит за этими файлами и ни за какими другими. Всё, чего нет в
 * таблице, запрещено.
 */
class BackendFileDownloadPolicy
{
    const IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'tif', 'bmp', 'ico'];

    /**
     * folder => [ file => [ ext => permission ] ]
     *
     * @var array
     */
    private static $map = [
        'export' => [
            'export'               => ['csv' => 'export'],
            'export_orders'        => ['csv' => 'orders'],
            'export_stat'          => ['csv' => 'category_stats'],
            'export_stat_products' => ['csv' => 'sales_report'],
        ],
        'export_users' => [
            'users'      => ['csv' => 'users'],
            'subscribes' => ['csv' => 'subscribes'],
        ],
        'import' => [
            'example' => ['csv' => 'import'],
            'import'  => ['csv' => 'import'],
        ],
    ];

    public function permissionFor($folder, $file, $ext)
    {
        if (!is_string($folder) || !is_string($file) || !is_string($ext)) {
            return null;
        }

        $ext = strtolower($ext);

        // Водяной знак лежит под настройками каталога и может иметь любое
        // из поддерживаемых расширений изображения.
        if ($folder === 'watermark' && $file === 'watermark') {
            return in_array($ext, self::IMAGE_EXTENSIONS, true) ? 'settings' : null;
        }

        if (!isset(self::$map[$folder][$file][$ext])) {
            return null;
        }

        return self::$map[$folder][$file][$ext];
    }
}
