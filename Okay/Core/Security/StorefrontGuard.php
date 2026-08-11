<?php


namespace Okay\Core\Security;


use Okay\Core\Request;
use Okay\Core\Response;

/**
 * Охорона мутуючих запитів вітрини.
 *
 * Та сама перевірка, що й у AbstractController::requireCustomerCsrf(), але
 * доступна там, де контролера немає: у хелперах, які обробляють POST
 * (CommonHelper, CommentsHelper), і в контролерах модулів.
 */
class StorefrontGuard
{
    private $request;
    private $response;

    public function __construct(Request $request, Response $response)
    {
        $this->request  = $request;
        $this->response = $response;
    }

    /**
     * Пропускає лише POST із коректним токеном вітрини, інакше завершує запит.
     */
    public function requireCustomerCsrf()
    {
        if (!$this->request->method('post')) {
            $this->reject(405, 'Method Not Allowed');
        }

        if (!CustomerCsrfToken::check($this->request->post('customer_csrf_token'))) {
            $this->reject(403, 'Forbidden');
        }
    }

    private function reject($statusCode, $message)
    {
        $this->response->setStatusCode($statusCode);
        $this->response->setContent($message, RESPONSE_TEXT);
        $this->response->sendContent();
        exit;
    }
}
