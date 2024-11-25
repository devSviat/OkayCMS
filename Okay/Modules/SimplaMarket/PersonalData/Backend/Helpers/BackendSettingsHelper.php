<?php

namespace Okay\Modules\SimplaMarket\PersonalData\Backend\Helpers;

use Okay\Core\Request;
use Okay\Core\Settings;
use Okay\Core\Modules\Extender\ExtenderFacade;


class BackendSettingsHelper
{
    /**
     * @var Settings
     */
    private $settings;

    /**
     * @var Request
     */
    private $request;

    public function __construct(
        Settings $settings,
        Request $request

    )
    {
        $this->settings = $settings;
        $this->request = $request;

    }


    public function updateGeneralSettings()
    {

        $this->settings->set('pd_comment', $this->request->post('pd_comment', 'boolean'));
        $this->settings->set('pd_cart', $this->request->post('pd_cart', 'boolean'));
        $this->settings->set('pd_register', $this->request->post('pd_register', 'boolean'));
        $this->settings->set('pd_feedback', $this->request->post('pd_feedback', 'boolean'));
        $this->settings->set('pd_callback', $this->request->post('pd_callback', 'boolean'));
        $this->settings->set('pd_fast_order', $this->request->post('pd_fast_order', 'boolean'));
        $this->settings->set('pd_comment_product', $this->request->post('pd_comment_product', 'boolean'));


        ExtenderFacade::execute(__METHOD__, null, func_get_args());
    }

}