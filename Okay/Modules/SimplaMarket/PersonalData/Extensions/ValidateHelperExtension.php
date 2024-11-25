<?php


namespace Okay\Modules\SimplaMarket\PersonalData\Extensions;


use Okay\Core\Request;
use Okay\Core\FrontTranslations;
use Okay\Core\Modules\Extender\ExtensionInterface;
use Okay\Core\Response;

class ValidateHelperExtension implements ExtensionInterface
{
    /**
     * @var Request
     */
    private $request;

    /**
     * @var FrontTranslations
     */
    private $frontTranslations;

    public function __construct(
        Response $response,
        Request $request,
        FrontTranslations $frontTranslations
    )
    {
        $this->response           = $response;
        $this->request           = $request;
        $this->frontTranslations = $frontTranslations;
    }

    public function extendGetUserRegisterError($error)
    {
        if (empty($error) && $this->notConfirmedDataProcessing()) {
            $error = 'empty_name';
            $this->replaceErrorMessage();
        }
        return $error;
    }

    public function extendGetFeedbackValidateError($error)
    {
        if (empty($error) && $this->notConfirmedDataProcessing()) {
            $error = 'empty_name';
            $this->replaceErrorMessage();
        }
        return $error;
    }

    public function extendGetCartValidateError($error)
    {
        if (empty($error) && $this->notConfirmedDataProcessing()) {
            $error = 'empty_name';
            $this->replaceErrorMessage();
        }
        return $error;
    }

    public function extendGetCallbackValidateError($error)
    {
        if (empty($error) && $this->notConfirmedDataProcessing()) {
            $error = 'empty_name';
            $this->replaceErrorMessage();
        }
        return $error;
    }

    public function extendGetCommentValidateError($error)
    {
        if (empty($error) && $this->notConfirmedDataProcessing()) {
            $error = 'empty_name';
            $this->replaceErrorMessage();
        }
        return $error;
    }



    public function extendGetSubscribeValidateError($error)
    {
        if (empty($error) && $this->notConfirmedDataProcessing()) {
            $error = 'empty_name';
            $this->replaceErrorMessage();
        }
        return $error;
    }

    private function replaceErrorMessage()
    {
        $this->frontTranslations->addTranslation(
            'form_enter_name',
            $this->frontTranslations->getTranslation('simplamarket__personal_data__confirm_error_message')
        );
    }

    private function notConfirmedDataProcessing()
    {
        return $this->request->post('simplamarket__personal_data__need_confirm') && ! $this->request->post('simplamarket__personal_data__confirm');
    }
}