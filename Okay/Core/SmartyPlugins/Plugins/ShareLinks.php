<?php


namespace Okay\Core\SmartyPlugins\Plugins;


use Okay\Core\Settings;
use Okay\Core\SmartyPlugins\Func;
use Okay\Core\SocialShare;

class ShareLinks extends Func
{
    protected $tag = 'share_links';

    private $socialShare;
    private $settings;

    public function __construct(SocialShare $socialShare, Settings $settings)
    {
        $this->socialShare = $socialShare;
        $this->settings    = $settings;
    }

    public function run($params, $smarty)
    {
        if (empty($params['var'])) {
            return;
        }

        $smarty->assign($params['var'], $this->socialShare->buildLinks(
            (array)$this->settings->get('sj_shares'),
            (string)($params['url'] ?? ''),
            (string)($params['title'] ?? '')
        ));
    }
}
