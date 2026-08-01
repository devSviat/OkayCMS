<?php


namespace Okay\Core\SmartyPlugins\Plugins;


use Okay\Core\SmartyPlugins\Modifier;

/**
 * Заміна модифікатору |key. Smarty не вміє передавати параметр за посиланням, тож
 * key() як модифікатор не працює навіть зареєстрований; тут ключ береться з власної
 * копії масиву. Парний до First, який так само підміняє |reset - з тією різницею,
 * що на порожньому масиві віддає null, бо це семантика array_key_first(), тоді як
 * reset() у First віддає false. У шаблоні обидва хибні.
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
