<?php


namespace Okay\Requests;


use Okay\Core\Modules\Extender\ExtenderFacade;
use Okay\Core\Phone;
use Okay\Core\Request;

class CommonRequest
{
    private $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * @return null|object
     */
    public function postComment()
    {
        $comment = null;
        if ($this->request->post('comment')) {
            $comment = new \stdClass;
            $comment->name = $this->text('name');
            $comment->email = $this->text('email');
            $comment->text = $this->text('text');
        }

        return ExtenderFacade::execute(__METHOD__, $comment, func_get_args());
    }

    public function postFeedback()
    {
        $feedback = null;
        if ($this->request->post('feedback')) {
            $feedback = new \stdClass;
            $feedback->email    = $this->text('email');
            $feedback->name     = $this->text('name');
            $feedback->message  = $this->text('message');
        }

        return ExtenderFacade::execute(__METHOD__, $feedback, func_get_args());
    }

    public function postCallback()
    {
        $callback = null;
        if ($this->request->post('callback')) {
            $callback = new \stdClass;
            $callback->phone    = Phone::toSave($this->request->post('callback_phone'));
            $callback->name     = $this->request->post('callback_name');
            $callback->url      = $this->request->getCurrentUrl();
            $callback->message  = $this->request->post('callback_message');
        }

        return ExtenderFacade::execute(__METHOD__, $callback, func_get_args());
    }

    public function postSubscribe()
    {
        $subscribe = null;
        if ($this->request->post('subscribe')) {
            $subscribe = new \stdClass;
            $subscribe->email = $this->text('subscribe_email');
        }

        return ExtenderFacade::execute(__METHOD__, $subscribe, func_get_args());
    }

    /**
     * Поля, що лягають у NOT NULL-колонки, беруться тільки через це.
     * Request::post() на відсутнє поле дає null, а явний null перебиває
     * значення за замовчуванням: вставка падає, і форма мовчки нічого не
     * зберігає. Так само зроблено в CartRequest для замовлення.
     *
     * callback->message сюди не входить навмисно - його колонка nullable.
     */
    private function text($name)
    {
        $value = $this->request->post($name);

        return is_scalar($value) ? (string)$value : '';
    }
}