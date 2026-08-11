<?php


namespace Okay\Controllers;


use Okay\Core\FrontPostRedirectGet;
use Okay\Core\Notify;
use Okay\Core\Router;
use Okay\Core\Security\FormToken;
use Okay\Entities\FeedbacksEntity;
use Okay\Helpers\ValidateHelper;
use Okay\Requests\CommonRequest;

class FeedbackController extends AbstractController {

    /** Форма зворотного зв'язку для FormToken. */
    const FEEDBACK_FORM = 'feedback';

    public function render(
        FeedbacksEntity $feedbacksEntity,
        Notify $notify,
        CommonRequest $commonRequest,
        ValidateHelper $validateHelper,
        FrontPostRedirectGet $frontPostRedirectGet
    ) {

        // Повідомлення з попереднього POST уже роздав CommonHelper::applyFlash()
        // із onInit(), тому тут його не забирають удруге - у сесії вже порожньо.

        if (($feedback = $commonRequest->postFeedback()) !== null) {
            $this->requireCustomerCsrf();

            if ($error = $validateHelper->getFeedbackValidateError($feedback)) {
                $this->design->assign('error', $error);
            } else {

                if ($this->acceptFeedback($feedback)) {
                    $feedback->ip = $_SERVER['REMOTE_ADDR'];
                    $feedback->lang_id = $_SESSION['lang_id'];
                    $feedbackId = $feedbacksEntity->add($feedback);

                    // Отправляем email
                    $notify->emailFeedbackAdmin($feedbackId);
                }

                // Відсіяний повтор бачить те саме, що й перша відправка: для
                // відвідувача лист пішов, і другого підтвердження він не просив.
                $frontPostRedirectGet->flash([
                    'message_sent' => true,
                    'request_data' => $this->design->getVar('request_data'),
                ]);
                $frontPostRedirectGet->redirectToCurrent();
            }
        }

        $this->design->assign('canonical', Router::generateUrl('page', ['url' => $this->page->url], true));

        $this->response->setContent('feedback.tpl');
    }

    private function acceptFeedback($feedback)
    {
        return FormToken::accept(
            self::FEEDBACK_FORM,
            $this->request->post('form_token'),
            $feedback,
            FormToken::ACCIDENT_TTL
        );
    }
}
