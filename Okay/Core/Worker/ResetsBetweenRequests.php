<?php

namespace Okay\Core\Worker;

/**
 * Сервіс, який переживає запит, але має що прибрати на його межі.
 *
 * Потрібен рідко: якщо сервіс тримає стан запиту, правильна відповідь - не
 * скидати його, а лишити request-scoped. Інтерфейс існує для випадків, де
 * повторне створення коштує помітно дорожче за прибирання.
 */
interface ResetsBetweenRequests
{
    public function resetRequestState(): void;
}
