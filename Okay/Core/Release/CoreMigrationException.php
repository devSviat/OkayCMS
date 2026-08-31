<?php

namespace Okay\Core\Release;

/**
 * Несе перелік core-міграцій, застосованих у цьому запуску до моменту
 * провалу — потрібен адмінці для rollback-попередження (спец §9).
 */
class CoreMigrationException extends \RuntimeException
{
    /** @param list<string> $appliedNames */
    public function __construct(string $message, public readonly array $appliedNames, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
