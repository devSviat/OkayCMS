<?php


namespace Okay\Modules\SimplaMarket\PersonalData\Init;

use Okay\Helpers\CartHelper;
use Okay\Helpers\ValidateHelper;
use Okay\Core\Modules\AbstractInit;
use Okay\Modules\SimplaMarket\PersonalData\Extensions\ValidateHelperExtension;

class Init extends AbstractInit
{
    public function install()
    {
        $this->setBackendMainController('DescriptionAdmin');
    }

    public function init()
    {
        $this->addPermission('simplamarket__personal_data');
        $this->registerBackendController('DescriptionAdmin');

        $this->registerChainExtension(
            [ValidateHelper::class,          'getUserRegisterError'],
            [ValidateHelperExtension::class, 'extendGetUserRegisterError']);

        $this->registerChainExtension(
            [ValidateHelper::class,          'getFeedbackValidateError'],
            [ValidateHelperExtension::class, 'extendGetFeedbackValidateError']);

        $this->registerChainExtension(
            [ValidateHelper::class,          'getCartValidateError'],
            [ValidateHelperExtension::class, 'extendGetCartValidateError']);

        $this->registerChainExtension(
            [ValidateHelper::class,          'getCallbackValidateError'],
            [ValidateHelperExtension::class, 'extendGetCallbackValidateError']);

        $this->registerChainExtension(
            [ValidateHelper::class,          'getCommentValidateError'],
            [ValidateHelperExtension::class, 'extendGetCommentValidateError']);

        $this->registerChainExtension(
            [ValidateHelper::class,          'getSubscribeValidateError'],
            [ValidateHelperExtension::class, 'extendGetSubscribeValidateError']);

        $this->addBackendControllerPermission('DescriptionAdmin', 'simplamarket__personal_data');
    }
}