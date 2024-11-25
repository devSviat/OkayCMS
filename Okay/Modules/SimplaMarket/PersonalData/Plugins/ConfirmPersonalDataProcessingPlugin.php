<?php


namespace Okay\Modules\SimplaMarket\PersonalData\Plugins;


use Okay\Core\Design;
use Okay\Core\SmartyPlugins\Func;

class ConfirmPersonalDataProcessingPlugin extends Func
{
    protected $tag = 'confirm_personal_data_processing';

    /**
     * @var Design
     */
    private $design;

    public function __construct(Design $design)
    {
        $this->design = $design;
    }

    public function run()
    {
        return $this->design->fetch('confirm_personal_data_processing.tpl');
    }
}