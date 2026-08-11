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
            // Мовчазний порожній вивід лишав би форму зовсім без захисту від
            // повтору, і побачити це можна було б хіба по дублях у базі.
            trigger_error('{form_token} викликано без обов\'язкового name', E_USER_WARNING);

            return '<!-- form_token: не вказано name -->';
        }

        $token = FormToken::get((string)$params['name']);

        return '<input type="hidden" name="form_token" value="' . htmlspecialchars($token, ENT_QUOTES) . '">';
    }
}
