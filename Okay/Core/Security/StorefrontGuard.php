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
     *
     * @param bool $asJson для ajax-ендпоінтів: відмова має приїхати в тому ж
     *                     форматі, що й успіх, інакше виклик її не розбере
     */
    public function requireCustomerCsrf($asJson = false)
    {
        if (!$this->request->method('post')) {
            $this->reject(405, 'Method Not Allowed', $asJson);
        }

        if (!CustomerCsrfToken::check($this->request->post('customer_csrf_token'))) {
            $this->reject(403, 'Forbidden', $asJson);
        }
    }

    /**
     * Пропускає лише POST, надісланий зі сторінки цього ж магазину.
     *
     * Рубіж, який не залежить від шаблону: працює й на темі, що не друкує
     * токен. Запит без Origin і без Referer відхиляється - доводити
     * походження мусить клієнт, а не застосунок за нього здогадуватись.
     *
     * @param bool $asJson
     * @return void
     */
    public function requireSameOrigin($asJson = false)
    {
        if (!$this->request->method('post')) {
            $this->reject(405, 'Method Not Allowed', $asJson);
        }

        $matches = RequestOrigin::matchesSite(
            isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : null,
            isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null,
            Request::getDomainWithProtocol()
        );

        if (!$matches) {
            $this->reject(403, 'Forbidden', $asJson);
        }
    }

    private function reject($statusCode, $message, $asJson)
    {
        $this->response->setStatusCode($statusCode);

        if ($asJson) {
            $this->response->setContent(json_encode(['errors' => [$message]]), RESPONSE_JSON);
        } else {
            $this->response->setContent($message, RESPONSE_TEXT);
        }

        $this->response->sendContent();
        exit;
    }
}
