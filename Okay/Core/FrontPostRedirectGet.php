<?php


namespace Okay\Core;


/**
 * Post/Redirect/Get для вітрини.
 *
 * Форма, яка після успішного запису рендерить сторінку прямо з POST, віддає
 * той самий запис на кожен F5. Редирект розриває цей звʼязок, а повідомлення
 * про успіх переїжджає на наступний GET через сесію.
 *
 * Аналог Okay\Core\BackendPostRedirectGet, але для дизайну вітрини: замість
 * пари фіксованих повідомлень переносить довільний набір змінних шаблону.
 */
class FrontPostRedirectGet
{
    const SESSION_KEY = 'front_prg';

    /**
     * @param array<string, mixed> $designVars змінні, які має отримати наступний GET
     */
    public function flash(array $designVars)
    {
        $_SESSION[self::SESSION_KEY] = $designVars;
    }

    /**
     * @return array<string, mixed>
     */
    public function match()
    {
        $flashed = $_SESSION[self::SESSION_KEY] ?? null;
        unset($_SESSION[self::SESSION_KEY]);

        return is_array($flashed) ? $flashed : [];
    }

    /**
     * 303, а не 302: браузер зобовʼязаний піти за адресою методом GET.
     */
    public function redirectToCurrent()
    {
        Response::redirectTo(Request::getCurrentUrl(), 303);
    }
}
