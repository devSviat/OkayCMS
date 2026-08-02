<?php
/**
 * Треба повернути масив об'єктів типу Okay\Core\TemplateConfig\Css
 * У конструктор об'єкта треба передати один обов'язковий параметр - назву файла
 * Якщо файл лежить не в стандартному місці (design/theme_name/css/),
 * треба вказати нове місце: викликати метод setDir() і передати шлях до файла відносно кореня сайта (DOCUMENT_ROOT)
 * Також можна викликати метод setPosition() і вказати head або footer (типово head)
 * @link ../../docs/assets.md
 */

use Okay\Core\TemplateConfig\Css;

return [
    (new Css('tokens.css')),
    (new Css('grid.css')),
    (new Css('vendor.css')),
    (new Css('select2.min.css')),
    (new Css('base.css')),
    (new Css('components.css')),
];
