<?php


namespace Okay\Admin\Requests;


use Okay\Core\Request;
use Okay\Core\Modules\Extender\ExtenderFacade;

class BackendPagesRequest
{
    /**
     * @var Request
     */
    private $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function postPage()
    {
        $page = new \stdClass;
        $page->id               = $this->request->post('id', 'integer');
        $page->name             = $this->request->post('name');
        $page->name_h1          = $this->request->post('name_h1');
        // post('url') тут навмисно без типу 'string', на відміну від решти
        // реквестів: його whitelist вирізає слеш, а url сторінки законно буває
        // складеним — user/login, user/register, user/password_remind. З типом
        // сторінка «Вхід» після першого ж збереження картки стала б userlogin.
        // is_string() закриває те, заради чого тип був потрібен: null із
        // відсутнього поля й масив із url[], на яких trim() падав.
        $postedUrl              = $this->request->post('url');
        $page->url              = is_string($postedUrl) ? trim($postedUrl) : '';
        // Регістр зводимо лише на створенні. У картці наявної сутності поле url
        // readonly (розблоковується кнопкою .fn_disable_url), тож при звичайному
        // збереженні воно повертається в POST незміненим — нормалізація там
        // мовчки перейменувала б сутність зі старим mixed-case урлом, а це 404
        // без 301 для всіх наявних посилань.
        if (empty($page->id)) {
            $page->url = mb_strtolower($page->url, 'UTF-8');
        }
        $page->visible          = $this->request->post('visible', 'boolean');
        $page->meta_title       = $this->request->post('meta_title');
        $page->meta_keywords    = $this->request->post('meta_keywords');
        $page->meta_description = $this->request->post('meta_description');
        $page->description      = $this->request->post('description');

        return ExtenderFacade::execute(__METHOD__, $page, func_get_args());
    }

    public function getId()
    {
        $id = $this->request->get('id', 'integer');
        return ExtenderFacade::execute(__METHOD__, $id, func_get_args());
    }

    public function postPositions()
    {
        $positions = $this->request->post('positions');
        return ExtenderFacade::execute(__METHOD__, $positions, func_get_args());
    }

    public function postCheck()
    {
        $check = $this->request->post('check');
        return ExtenderFacade::execute(__METHOD__, $check, func_get_args());
    }

    public function postAction()
    {
        $action = $this->request->post('action');
        return ExtenderFacade::execute(__METHOD__, $action, func_get_args());
    }
}