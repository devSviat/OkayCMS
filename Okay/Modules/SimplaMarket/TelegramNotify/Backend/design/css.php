<?php
/**
 * Нужно вернуть массив объектов типа Okay\Core\TemplateConfig\Css
 * В конструктор объекта нужно передать один обязательный параметр - название файла
 * Если скрипт лежит не в стандартном месте (design/theme_name/css/)
 * нужно указать новое место, вызвав метод setDir() и передать путь к файл относительно корня сайта (DOCUMENT_ROOT)
 * Также можно вызвать метод setPosition() и указать head или footer (по умолчанию head)
 * todo ссылка на документацию
 * @link https://github.com/OkayCMS/Okay3/blob/master/docs/js_css_files.md
 */

use Okay\Core\TemplateConfig\Css;

return [
    (new Css('jquery.datetimepicker.css')),

];

