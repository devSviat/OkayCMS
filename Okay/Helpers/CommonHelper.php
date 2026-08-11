<?php


namespace Okay\Helpers;


use Okay\Core\Design;
use Okay\Core\EntityFactory;
use Okay\Core\FrontPostRedirectGet;
use Okay\Core\Modules\Extender\ExtenderFacade;
use Okay\Core\Notify;
use Okay\Core\Request;
use Okay\Core\Security\FormToken;
use Okay\Core\Security\StorefrontGuard;
use Okay\Entities\CallbacksEntity;
use Okay\Entities\SubscribesEntity;
use Okay\Requests\CommonRequest;

class CommonHelper
{
    /** Форма зворотного дзвінка для FormToken. */
    const CALLBACK_FORM = 'callback';

    private $validateHelper;
    private $notify;
    private $design;
    private $commonRequest;
    private $entityFactory;
    private $userHelper;
    private $storefrontGuard;
    private $frontPostRedirectGet;
    private $request;

    public function __construct(
        ValidateHelper $validateHelper,
        Notify $notify,
        Design $design,
        CommonRequest $commonRequest,
        EntityFactory $entityFactory,
        UserHelper $userHelper,
        StorefrontGuard $storefrontGuard,
        FrontPostRedirectGet $frontPostRedirectGet,
        Request $request
    ) {
        $this->validateHelper = $validateHelper;
        $this->notify = $notify;
        $this->design = $design;
        $this->commonRequest = $commonRequest;
        $this->entityFactory = $entityFactory;
        $this->userHelper = $userHelper;
        $this->storefrontGuard = $storefrontGuard;
        $this->frontPostRedirectGet = $frontPostRedirectGet;
        $this->request = $request;
    }

    public function rootPostProcedure()
    {
        // Метод виконується з AbstractController::onInit(), тобто на кожній
        // сторінці. Тому спершу віддаємо назад те, що поклав попередній POST,
        // і лише потім розбираємо новий.
        $this->applyFlash();

        $callback  = $this->commonRequest->postCallback();
        $subscribe = $this->commonRequest->postSubscribe();

        // Обидві гілки пишуть у базу, тож охорона одна і на вході. Раніше її
        // не було зовсім: форма токен слала, сервер його не читав.
        if ($callback !== null || $subscribe !== null) {
            $this->storefrontGuard->requireCustomerCsrf();
        }

        $redirect = false;

        if ($callback !== null && $this->processCallback($callback)) {
            $redirect = true;
        }

        // Токена тут немає свідомо: повторна підписка тим самим email рядка не
        // створює - її відсіює перевірка унікальності у
        // ValidateHelper::getSubscribeValidateError().
        if ($subscribe !== null && $this->processSubscribe($subscribe)) {
            $redirect = true;
        }

        if ($redirect === true) {
            $this->frontPostRedirectGet->flash($this->flashPayload());
            $this->frontPostRedirectGet->redirectToCurrent();
        }

        ExtenderFacade::execute(__METHOD__, null, func_get_args());
    }

    /**
     * @return bool чи потрібен редирект: запис відбувся або повтор відсіяно
     */
    private function processCallback($callback)
    {
        if ($error = $this->validateHelper->getCallbackValidateError($callback)) {
            $this->design->assign('call_error', $error, true);
            return false;
        }

        if (!$this->acceptCallback($callback)) {
            // Заявка вже прийнята цим сеансом. Для відвідувача це успіх:
            // другого підтвердження він не просив.
            $this->design->assign('call_sent', true, true);
            return true;
        }

        /** @var CallbacksEntity $callbacksEntity */
        $callbacksEntity = $this->entityFactory->get(CallbacksEntity::class);

        if ($callbackId = $callbacksEntity->add($callback)) {
            $this->design->assign('call_sent', true, true);
            // Отправляем email
            $this->notify->emailCallbackAdmin($callbackId);
            return true;
        }

        $this->design->assign('call_error', 'unknown error', true);
        return false;
    }

    /**
     * @return bool
     */
    private function processSubscribe($subscribe)
    {
        /** @var SubscribesEntity $subscribesEntity */
        $subscribesEntity = $this->entityFactory->get(SubscribesEntity::class);

        /*Валидация данных клиента*/
        if ($error = $this->validateHelper->getSubscribeValidateError($subscribe)) {
            $this->design->assign('subscribe_error', $error, true);
            return false;
        }

        if ($subscribesEntity->add($subscribe)) {
            $this->design->assign('subscribe_success', true, true);
            return true;
        }

        return false;
    }

    private function acceptCallback($callback)
    {
        return FormToken::accept(self::CALLBACK_FORM, $this->request->post('form_token'), $callback);
    }

    /**
     * Що має пережити редирект: прапорці результату й дані форми - шаблони
     * друкують із них ім'я відправника у повідомленні про успіх.
     */
    private function flashPayload()
    {
        $vars = ['request_data' => $this->design->getVar('request_data')];

        foreach (['call_sent', 'call_error', 'subscribe_success', 'subscribe_error'] as $var) {
            if (($value = $this->design->getVar($var)) !== null) {
                $vars[$var] = $value;
            }
        }

        return $vars;
    }

    private function applyFlash()
    {
        foreach ($this->frontPostRedirectGet->match() as $var => $value) {
            $this->design->assign($var, $value, true);
        }
    }
}
