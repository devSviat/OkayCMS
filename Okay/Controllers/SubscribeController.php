<?php


namespace Okay\Controllers;


use Okay\Core\EntityFactory;
use Okay\Core\Request;
use Okay\Core\Response;
use Okay\Core\Security\CustomerCsrfToken;
use Okay\Entities\SubscribesEntity;
use Okay\Helpers\ValidateHelper;
use Okay\Requests\CommonRequest;

class SubscribeController
{

    public function ajaxSubscribe(
        CommonRequest $commonRequest,
        ValidateHelper $validateHelper,
        EntityFactory $entityFactory,
        Response $response,
        Request $request
    ) {
        // Контролер не успадковує AbstractController, тому перевіряємо
        // токен напряму.
        if (($subscribe = $commonRequest->postSubscribe()) !== null) {
            $this->requireCustomerCsrf($request, $response);

            /** @var SubscribesEntity $subscribesEntity */
            $subscribesEntity = $entityFactory->get(SubscribesEntity::class);

            /*Валидация данных клиента*/
            if ($error = $validateHelper->getSubscribeValidateError($subscribe)) {
                $result = [
                    'error' => $error,
                ];
            } elseif ($subscribeId = $subscribesEntity->add($subscribe)) {
                $result = [
                    'success' => true,
                ];
            } else {
                $result = [
                    'error' => 'Subscribe error',
                ];
            }
        } else {
            $result = [
                'error' => 'Empty data',
            ];
        }

        $response->setContent(json_encode($result), RESPONSE_JSON);
    }

    /**
     * Пропускає лише POST із коректним токеном вітрини.
     *
     * @param Request $request
     * @param Response $response
     * @return void
     */
    private function requireCustomerCsrf(Request $request, Response $response)
    {
        if (!$request->method('post')) {
            $response->setStatusCode(405);
            $response->setContent('Method Not Allowed', RESPONSE_TEXT);
            $response->sendContent();
            exit;
        }

        if (!CustomerCsrfToken::check($request->post('customer_csrf_token'))) {
            $response->setStatusCode(403);
            $response->setContent('Forbidden', RESPONSE_TEXT);
            $response->sendContent();
            exit;
        }
    }

}
