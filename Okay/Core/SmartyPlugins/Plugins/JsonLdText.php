<?php


namespace Okay\Core\SmartyPlugins\Plugins;


use Okay\Core\SmartyPlugins\Modifier;

class JsonLdText extends Modifier
{
    protected $tag = 'json_ld_text';

    /**
     * Готує текст до підстановки між лапками всередині JSON-LD.
     *
     * Екранування робить json_encode, а не htmlspecialchars: усередині <script>
     * сутності не декодуються, тож лапка осідала б у розмітці як &quot;. Він же
     * прибирає керуючі символи - від \r і табуляції весь блок стає невалідним,
     * і пошуковик відкидає його цілком.
     */
    public function run(string $str) : string
    {
        $encoded = json_encode(trim(strip_tags($str)), JSON_INVALID_UTF8_SUBSTITUTE);
        if ($encoded === false) {
            return '';
        }

        // Обгортка лапками лишається за шаблоном.
        return substr($encoded, 1, -1);
    }
}
