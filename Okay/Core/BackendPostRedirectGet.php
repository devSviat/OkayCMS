<?php


namespace Okay\Core;


class BackendPostRedirectGet
{
    /**
     * @var Request
     */
    private $request;

    /**
     * @var Response
     */
    private $response;

    private BackendTranslations $backendTranslations;

    public function __construct(Request $request, Response $response, BackendTranslations $backendTranslations)
    {
        $this->request  = $request;
        $this->response = $response;
        $this->backendTranslations = $backendTranslations;
    }

    public function __destruct()
    {
        if ($this->request->method('get')) {
            $this->clearStates();
        }
    }

    private function clearStates()
    {
        unset($_SESSION['new_entity_id']);
        unset($_SESSION['message_error']);
        unset($_SESSION['message_success']);
    }

    public function redirect($urlRedirectToList = '')
    {
        if (!empty($urlRedirectToList) && filter_var($urlRedirectToList, FILTER_VALIDATE_URL)) {
            $this->response->redirectTo($urlRedirectToList);
        }

        if (empty($_SESSION['new_entity_id'])) {
            $this->response->redirectTo($this->request->url());
        }

        $this->response->redirectTo($this->request->url(['id' => $_SESSION['new_entity_id']]));
    }

    /**
     * Entity::add() на невдалій вставці віддає false: Database ковтає виняток
     * і пише лише в лог. Порожній id тут — це «не додано», а не «додано».
     * Запобіжник для модулів, що не викликають requireAdded().
     */
    public function storeNewEntityId($id)
    {
        if (empty($id)) {
            $this->storeAddFailed();
            return;
        }

        $_SESSION['new_entity_id'] = $id;
    }

    /**
     * Викликається одразу після add(): на невдалій вставці повертає на ту саму
     * форму з помилкою, не даючи коду далі писати пов'язані дані до запису,
     * якого немає.
     */
    public function requireAdded($id): void
    {
        if (!empty($id)) {
            return;
        }

        $this->storeAddFailed();
        $this->response->redirectTo($this->request->url());
    }

    private function storeAddFailed(): void
    {
        unset($_SESSION['message_success'], $_SESSION['new_entity_id']);
        $_SESSION['message_error'] = $this->backendTranslations->getTranslation('general_add_failed');
    }

    public function storeMessageError($message)
    {
        $_SESSION['message_error'] = $message;
    }

    public function storeMessageSuccess($message)
    {
        $_SESSION['message_success'] = $message;
    }

    public function matchMessageError()
    {
        if (empty($_SESSION['message_error'])) {
            return false;
        }

        $messageError = $_SESSION['message_error'];
        unset($_SESSION['message_error']);
        return $messageError;
    }

    public function matchMessageSuccess()
    {
        if (empty($_SESSION['message_success'])) {
            return false;
        }

        $messageSuccess = $_SESSION['message_success'];
        unset($_SESSION['message_success']);
        return $messageSuccess;
    }

    public function getUrlRedirectToList($controllerNameList): string
    {
        return htmlspecialchars_decode($this->request->getRootUrl() . urldecode($this->request->get(
                'return',
                null,
                '/backend/index.php?controller=' . $controllerNameList
            )));
    }
}