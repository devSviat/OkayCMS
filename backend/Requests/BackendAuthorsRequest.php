<?php


namespace Okay\Admin\Requests;


use Okay\Core\Request;
use Okay\Core\Translit;
use Okay\Core\Modules\Extender\ExtenderFacade;

class BackendAuthorsRequest
{
    /**
     * @var Request
     */
    private $request;

    /**
     * @var Translit
     */
    private $translit;

    public function __construct(Request $request, Translit $translit)
    {
        $this->request = $request;
        $this->translit = $translit;
    }

    public function postAuthor()
    {
        $author = new \stdClass();
        $author->id               = $this->request->post('id', 'integer');
        $author->name             = $this->request->post('name');
        $author->position_name    = $this->request->post('position_name');
        $author->description      = $this->request->post('description');
        $author->visible          = $this->request->post('visible', 'boolean');
        // Зводимо на будь-якому збереженні, не лише на створенні. Поле url у
        // картці наявної сутності readonly, але readonly-поле браузер усе одно
        // надсилає — тож сутність зі старим mixed-case урлом нормалізується при
        // першому ж збереженні картки. Це свідомо: інакше такий url лишався б
        // назавжди й далі розходився б із _ci-порівнянням у базі. Магазинам, де
        // такі url є, це міняє адреси — див. примітку до релізу.
        $author->url              = mb_strtolower(trim($this->request->post('url', 'string')), 'UTF-8');
        $author->meta_title       = $this->request->post('meta_title');
        $author->meta_keywords    = $this->request->post('meta_keywords');
        $author->meta_description = $this->request->post('meta_description');

        $socials = [];
        if ($postSocials = $this->request->post('socials')) {
            foreach ($postSocials as $social) {
                if (!empty($social['url'])) {
                    $socials[] = $social;
                }
            }
        }
        
        $author->socials = json_encode($socials);
        return ExtenderFacade::execute(__METHOD__, $author, func_get_args());
    }

    public function postDeleteImage()
    {
        $deleteImage = $this->request->post('delete_image');
        return ExtenderFacade::execute(__METHOD__, $deleteImage, func_get_args());
    }

    public function fileImage()
    {
        $image = $this->request->files('image');
        return ExtenderFacade::execute(__METHOD__, $image, func_get_args());
    }

    public function postCheck()
    {
        $check = (array) $this->request->post('check');
        return ExtenderFacade::execute(__METHOD__, $check, func_get_args());
    }

    public function postAction()
    {
        $action = $this->request->post('action');
        return ExtenderFacade::execute(__METHOD__, $action, func_get_args());
    }

    public function postPositions()
    {
        $positions = $this->request->post('positions');
        return ExtenderFacade::execute(__METHOD__, $positions, func_get_args());
    }
}