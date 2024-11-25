<?php


namespace Okay\Modules\SimplaMarket\InformBackInStock\Plugins;


use Okay\Core\Design;
use Okay\Core\SmartyPlugins\Func;

class ReportInStockPlugin extends Func
{
    protected $tag = 'report_in_stock';

    protected $design;

    public function __construct(Design $design)
    {
        $this->design = $design;
    }

    public function run($vars)
    {
        $this->design->assign('report_in_stock_product', $vars['product']);
        return $this->design->fetch('inform_stock_btn.tpl');
    }
}