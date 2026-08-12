<?php


namespace Okay\Controllers;


use Okay\Core\EntityFactory;
use Okay\Core\Request;
use Okay\Core\Response;
use Okay\Core\Security\StorefrontGuard;
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
        StorefrontGuard $storefrontGuard
    ) {
        // Контролер не успадковує AbstractController, тому охорону бере
        // сервісом - тим самим, що й хелпери, які обробляють POST.
        if (($subscribe = $commonRequest->postSubscribe()) !== null) {
            $storefrontGuard->requireCustomerCsrf();

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
}
