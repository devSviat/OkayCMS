<?php
/**
 * Треба повернути масив об'єктів типу Okay\Core\TemplateConfig\Js
 * У конструктор об'єкта треба передати один обов'язковий параметр - назву файла
 * Якщо файл лежить не в стандартному місці (design/theme_name/js/),
 * треба вказати нове місце: викликати метод setDir() і передати шлях до файла відносно кореня сайта (DOCUMENT_ROOT)
 * Також можна викликати метод setPosition() і вказати head або footer (типово head)
 * @link https://github.com/OkayCMS/Okay3/blob/master/docs/js_css_files.md
 */

use Okay\Core\TemplateConfig\Js;

return [
    //(new Js('jquery-3.4.1.min.js')),
    (new Js('swiper-bundle.min.js')),
    (new Js('nouislider.min.js'))->setPosition('footer'),
    (new Js('select2.min.js'))->setPosition('footer'),
    (new Js('okay.js'))->setPosition('footer'),
    (new Js('lazyload.min.js'))->setPosition('footer'),
    //(new Js('jquery.fancybox.min.js'))->setPosition('footer'),
    (new Js('readmore.min.js'))->setPosition('footer'),
    (new Js('mobile_menu.js'))->setPosition('footer'),
    (new Js('sticky.min.js'))->setPosition('footer'),
    (new Js('vibe.js'))->setPosition('footer'),
    //(new Js('jquery.autocomplete-min.js'))->setPosition('footer'),
    //(new Js('jquery.validate.min.js'))->setPosition('footer'),
];
