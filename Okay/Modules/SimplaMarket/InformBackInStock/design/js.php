<?php

use Okay\Core\TemplateConfig\Js;

return [
    (new Js('inform_stock.js'))->setDefer(true)->setPosition('footer'),
];
