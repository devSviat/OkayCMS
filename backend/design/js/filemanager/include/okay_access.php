<?php

/**
 * Єдина точка перевірки доступу для процедурних входів файлового менеджера.
 *
 * Підключається ПЕРШИМ рядком кожної точки входу: до читання конфігурації,
 * до include утиліт і до будь-яких операцій із файловою системою.
 */

use Okay\Core\EntityFactory;
use Okay\Core\Security\Filemanager\AccessGuard;
use Okay\Core\Security\Filemanager\PathResolver;
use Okay\Core\Security\SecurityHeaders;
use Okay\Core\Security\SessionNames;

$okayFilemanagerRoot = realpath(__DIR__ . '/../../../../..');

require_once $okayFilemanagerRoot . '/vendor/autoload.php';

// Ці входи не проходять через Okay\Core\Response, а dialog.php віддає
// HTML-документ.
foreach (SecurityHeaders::defaults() as $okayHeader) {
    header($okayHeader);
}

SessionNames::startBackend();

// Контейнер резолвить шляхи відносно кореня проєкту, а точки входу
// працюють зі своєї директорії — перемикаємось на час bootstrap.
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
 * Єдиний відсів шляхів із запиту.
 *
 * Далі по коду ці значення склеюються з config['current_path'] більш ніж
 * у двадцяти місцях. Відхилити traversal один раз тут надійніше, ніж
 * правити кожну склейку у вендорному коді.
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
