<?php


namespace Okay\Core\SmartyPlugins\Plugins;


use Okay\Core\SmartyPlugins\Modifier;

/**
 * Заміна модифікатору |key. Smarty не вміє передавати параметр за посиланням, тож
 * key() як модифікатор не працює навіть зареєстрований; тут ключ береться з власної
 * копії масиву. Парний до First, який так само підміняє |reset.
 */
class FirstKey extends Modifier
{
    protected $tag = 'first_key';

    public function run($params = [])
    {
        if (!is_array($params)) {
            return false;
        }

        return array_key_first($params);
    }
}
