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
     * Повідомлення живе рівно стільки, скільки триває перехід за редиректом.
     * Без строку воно чекало б у сесії скільки завгодно: покупець міг не
     * дійти до тієї сторінки взагалі, а через годину повернутись на неї й
     * побачити «заявку прийнято» з давно набраними даними у формі.
     */
    const TTL = 60;

    /**
     * @param array<string, mixed> $designVars змінні, які має отримати наступний GET
     */
    public function flash(array $designVars)
    {
        $_SESSION[self::SESSION_KEY] = [
            'url'        => Request::getCurrentUrl(),
            'expires_at' => time() + self::TTL,
            'vars'       => self::withoutTokens($designVars),
        ];
    }

    /**
     * request_data - це копія $_POST, тобто разом із полями форми там лежать
     * обидва токени. Далі вони не потрібні й у сховище потрапити не мають.
     */
    private static function withoutTokens(array $designVars)
    {
        if (!isset($designVars['request_data']) || !is_array($designVars['request_data'])) {
            return $designVars;
        }

        unset(
            $designVars['request_data']['customer_csrf_token'],
            $designVars['request_data']['form_token']
        );

        return $designVars;
    }

    /**
     * Забирає повідомлення лише той GET, якому воно адресоване.
     *
     * Перевірка адреси тут не для краси: сторінку супроводжують підзапити
     * /resize і dynamic_js, їхні контролери теж успадковують
     * AbstractController, і будь-який із них, завершившись раніше за GET за
     * редиректом, з'їв би повідомлення - покупець побачив би порожню форму й
     * надіслав заявку вдруге.
     *
     * @return array<string, mixed>
     */
    public function match()
    {
        $flashed = $_SESSION[self::SESSION_KEY] ?? null;

        if (!is_array($flashed) || !isset($flashed['url'], $flashed['vars'], $flashed['expires_at'])) {
            unset($_SESSION[self::SESSION_KEY]);

            return [];
        }

        if ((int)$flashed['expires_at'] < time()) {
            unset($_SESSION[self::SESSION_KEY]);

            return [];
        }

        if ($flashed['url'] !== Request::getCurrentUrl()) {
            return [];
        }

        unset($_SESSION[self::SESSION_KEY]);

        return is_array($flashed['vars']) ? $flashed['vars'] : [];
    }

    /**
     * 303, а не 302: браузер зобовʼязаний піти за адресою методом GET.
     */
    public function redirectToCurrent()
    {
        Response::redirectTo(Request::getCurrentUrl(), 303);
    }
}
