<?php

namespace Okay\Core\Security;

/**
 * Білий список того, що взагалі можна завантажити через backend/files/index.php,
 * і права, які для цього потрібні.
 *
 * Набір збігається з rewrite-правилами nginx (див. docs/nginx/nginx.conf):
 * адмінка ходить за цими файлами і ні за якими іншими. Усе, чого немає в
 * таблиці, заборонено.
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

        // Водяний знак лежить під налаштуваннями каталогу й може мати будь-яке
        // з підтримуваних розширень зображення.
        if ($folder === 'watermark' && $file === 'watermark') {
            return in_array($ext, self::IMAGE_EXTENSIONS, true) ? 'settings' : null;
        }

        if (!isset(self::$map[$folder][$file][$ext])) {
            return null;
        }

        return self::$map[$folder][$file][$ext];
    }
}
