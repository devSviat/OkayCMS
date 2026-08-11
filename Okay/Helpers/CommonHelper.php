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

        // Розширення - до редиректу: Response::redirectTo() завершується exit,
        // тож усе, що стоїть після нього, не виконується взагалі. Модуль, який
        // шле заявку в CRM, інакше мовчки не спрацьовував би саме на успіху.
        ExtenderFacade::execute(__METHOD__, null, func_get_args());

        if ($redirect === true) {
            $this->frontPostRedirectGet->flash($this->flashPayload());
            $this->frontPostRedirectGet->redirectToCurrent();
        }
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

        // Запис не пройшов, тож повтором ця відправка не є. Без release()
        // відбиток лишався б зайнятим до кінця вікна, і негайна друга спроба
        // тієї самої заявки пішла б гілкою дубля - тобто показала б
        // «прийнято», не створивши рядка. Так само роблять FeedbackController
        // і CommentsHelper.
        FormToken::release(self::CALLBACK_FORM, $this->request->post('form_token'));

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

    /**
     * Вікно відбитка тут коротке. Заявка коштує дешево, а втрачена - дорого:
     * покупець, якому не додзвонились, за десять хвилин цілком може лишити ту
     * саму заявку ще раз, і вона має дійти.
     */
    private function acceptCallback($callback)
    {
        return FormToken::accept(
            self::CALLBACK_FORM,
            $this->request->post('form_token'),
            $callback,
            FormToken::ACCIDENT_TTL
        );
    }

    /** Прапорці результату, які шаблони показують після редиректу. */
    const FLASH_FLAGS = ['call_sent', 'call_error', 'subscribe_success', 'subscribe_error'];

    /**
     * Що має пережити редирект: прапорці результату й дані форми - шаблони
     * друкують із них ім'я відправника у повідомленні про успіх.
     */
    private function flashPayload()
    {
        $vars = ['request_data' => $this->design->getVar('request_data')];

        foreach (self::FLASH_FLAGS as $var) {
            if (($value = $this->design->getVar($var)) !== null) {
                $vars[$var] = $value;
            }
        }

        return $vars;
    }

    /**
     * Третій аргумент assign() - це $dynamicJs, а не «глобально». Прапорці
     * результату його мали й до PRG, тож вони його зберігають; решта, зокрема
     * дані форми, у dynamic_js не їде.
     */
    private function applyFlash()
    {
        foreach ($this->frontPostRedirectGet->match() as $var => $value) {
            $this->design->assign($var, $value, in_array($var, self::FLASH_FLAGS, true));
        }
    }
}
