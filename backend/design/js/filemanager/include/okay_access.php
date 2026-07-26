<?php

/**
 * Единая точка проверки доступа для процедурных входов файлового менеджера.
 *
 * Подключается ПЕРВОЙ строкой каждой точки входа: до чтения конфигурации,
 * до include утилит и до любых операций с файловой системой.
 */

use Okay\Core\EntityFactory;
use Okay\Core\Security\Filemanager\AccessGuard;
use Okay\Core\Security\Filemanager\PathResolver;
use Okay\Core\Security\SessionNames;

$okayFilemanagerRoot = realpath(__DIR__ . '/../../../../..');

require_once $okayFilemanagerRoot . '/vendor/autoload.php';

SessionNames::startBackend();

// Контейнер резолвит пути относительно корня проекта, а точки входа
// работают из своей директории — переключаемся на время bootstrap.
$okayPreviousCwd = getcwd();
chdir($okayFilemanagerRoot);

$okayDI = include $okayFilemanagerRoot . '/Okay/Core/config/container.php';

/** @var EntityFactory $okayEntityFactory */
$okayEntityFactory = $okayDI->get(EntityFactory::class);

$okayAccessGuard = new AccessGuard($okayEntityFactory);
$okayManager = $okayAccessGuard->requireManager();

if ($okayPreviousCwd !== false) {
    chdir($okayPreviousCwd);
}

/**
 * Единый отсев путей из запроса.
 *
 * Дальше по коду эти значения склеиваются с config['current_path'] более
 * чем в двадцати местах. Отклонить traversal один раз здесь надёжнее, чем
 * править каждую склейку в вендорном коде.
 */
$okayPathParams = ['path', 'file', 'name', 'fldr', 'new_path', 'old_path', 'folder', 'sub_folder'];

foreach ($okayPathParams as $okayParam) {
    foreach ([$_GET, $_POST] as $okaySource) {
        if (!isset($okaySource[$okayParam])) {
            continue;
        }

        $okayValue = $okaySource[$okayParam];

        if (is_array($okayValue)) {
            foreach ($okayValue as $okayItem) {
                if (!PathResolver::isSafeRelativePath($okayItem)) {
                    okay_filemanager_reject_path();
                }
            }
            continue;
        }

        if (!PathResolver::isSafeRelativePath($okayValue)) {
            okay_filemanager_reject_path();
        }
    }
}

function okay_filemanager_reject_path()
{
    if (!headers_sent()) {
        header('HTTP/1.1 400 Bad Request');
        header('Content-Type: text/plain; charset=utf-8');
    }

    echo 'Bad Request';
    exit;
}
