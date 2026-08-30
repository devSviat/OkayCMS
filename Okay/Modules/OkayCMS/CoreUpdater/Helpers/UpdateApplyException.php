<?php

namespace Okay\Modules\OkayCMS\CoreUpdater\Helpers;

/**
 * Несе перелік файлів, застосованих (чи відновлених) у цьому запуску до
 * моменту провалу — потрібен UpdateRunner для rollback-звіту (спек §9).
 * Аналог Okay\Core\Release\CoreMigrationException.
 */
class UpdateApplyException extends \RuntimeException
{
    /** @param list<string> $appliedPaths */
    public function __construct(string $message, public readonly array $appliedPaths, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
