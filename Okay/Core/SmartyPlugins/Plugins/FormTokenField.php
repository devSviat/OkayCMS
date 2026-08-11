<?php


namespace Okay\Core\SmartyPlugins\Plugins;


use Okay\Core\Security\FormToken;
use Okay\Core\SmartyPlugins\Func;

/**
 * {form_token name="callback"} - готове приховане поле форми, яка щось пише.
 *
 * Одне ім'я поля на всі форми, різні значення за іменем форми.
 */
class FormTokenField extends Func
{
    protected $tag = 'form_token';

    public function run($params, $smarty)
    {
        if (empty($params['name'])) {
            return '';
        }

        $token = FormToken::get((string)$params['name']);

        return '<input type="hidden" name="form_token" value="' . htmlspecialchars($token, ENT_QUOTES) . '">';
    }
}
