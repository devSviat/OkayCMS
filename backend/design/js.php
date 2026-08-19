<?php
/**
 * Нужно вернуть массив объектов типа Okay\Core\TemplateConfig\Js
 * В конструктор объекта нужно передать один обязательный параметр - название файла
 * Если скрипт лежит не в стандартном месте (design/theme_name/js/)
 * нужно указать новое место, вызвав метод setDir() и передать путь к файл относительно корня сайта (DOCUMENT_ROOT)
 * Также можно вызвать метод setPosition() и указать head или footer (по умолчанию head)
 * todo ссылка на документацию
 */

use Okay\Core\TemplateConfig\Js;

return [
    (new Js('jquery/jquery.js')),
    (new Js('jquery/jquery-migrate.js')),
    (new Js('okay-dropdown.js')),
    (new Js('bootstrap-select.js')),
    (new Js('a11y-dialog/a11y-dialog.js')),
    (new Js('okay-modal.js')),
    (new Js('okay-scrollbar.js')),
    (new Js('intro_js/intro.js')),
    (new Js('toastr.min.js')),
    (new Js('Sortable.js')),
    (new Js('okay-datepicker.js')),
];
