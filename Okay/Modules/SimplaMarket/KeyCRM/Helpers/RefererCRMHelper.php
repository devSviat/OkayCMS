<?php

namespace Okay\Modules\SimplaMarket\KeyCRM\Helpers;

use Okay\Core\EntityFactory;
use Okay\Core\Modules\Extender\ExtensionInterface;
use Okay\Core\Request;
use Snowplow\RefererParser\Parser;

class RefererCRMHelper implements ExtensionInterface
{
    const CHANNEL_EMAIL     = 'email';
    const CHANNEL_SEARCH    = 'search';
    const CHANNEL_SOCIAL    = 'social';
    const CHANNEL_REFERRAL  = 'referral';
    const CHANNEL_UNKNOWN   = 'unknown';

    /* EntityFactory $entityFactory */
    public $entityFactory;

    /* Request $request */
    public $request;

    /** @var Parser $parser */
    private $parser;

    private static $userRefererCRM;

    public function __construct(
        EntityFactory   $entityFactory,
        Request         $request,
        Parser          $parser
    ){
        $this->entityFactory = $entityFactory;
        $this->request       = $request;
        $this->parser        = $parser;
    }

    //  отслеживаем на каждой странице переход из внешнего ресурса для отлова UTM меток
    public function commonAfterControllerProcedure($metadataHelper)
    {
        if (!$this->getCrmUserReferer()) {
            $this->getSearchTerm();
        }
        if (!empty($_GET['utm_medium'])) {
            $this->addUserRefererBitrix($this->getCrmUserReferer());
        }
        return $metadataHelper;
    }

    public function addUserRefererBitrix($referer)
    {
        $userRefererCRM = [
            'searchTerm'   => $referer['searchTerm'],
            'utm_medium'   => !empty($_GET['utm_medium']) ? $_GET['utm_medium'] : null,
            'utm_source'   => !empty($_GET['utm_source']) ? $_GET['utm_source'] : null,
            'utm_campaign' => !empty($_GET['utm_campaign']) ? $_GET['utm_campaign'] : null,
            'utm_term'     => !empty($_GET['utm_term']) ? $_GET['utm_term'] : null,
            'utm_content'  => !empty($_GET['utm_content']) ? $_GET['utm_content'] : null
        ];

        $this->saveUserRefererCRM($userRefererCRM);
    }

    private function saveUserRefererCRM(array $referer)
    {
        self::$userRefererCRM = $referer;
        setcookie('userRefererCRM', base64_encode(json_encode($referer)), time() + 3 * 24 * 60 * 60, '/', '', false, false);
    }

    public function getSearchTerm()
    {
        $userRefererCRM = null;
        $referer = $this->parser->parse(
            Request::getReferer(),
            Request::getCurrentUrl()
        );

        if ($referer->isKnown()) {
            switch ($referer->getMedium()) {
                case self::CHANNEL_EMAIL:
                    $userRefererCRM = [
                        'medium'     => self::CHANNEL_EMAIL,
                        'source'     => $referer->getSource(),
                        'searchTerm' => $referer->getSearchTerm(),
                    ];
                    break;
                case self::CHANNEL_SEARCH:
                    $userRefererCRM = [
                        'medium'     => self::CHANNEL_SEARCH,
                        'source'     => $referer->getSource(),
                        'searchTerm' => $referer->getSearchTerm(),
                    ];
                    break;
                case self::CHANNEL_SOCIAL:
                    $userRefererCRM = [
                        'medium'     => self::CHANNEL_SOCIAL,
                        'source'     => $referer->getSource(),
                        'searchTerm' => $referer->getSearchTerm(),
                    ];
                    break;
            }
        } elseif (($referer = Request::getReferer()) && !$this->isInternalUrl($referer)) {
            $userRefererCRM = [
                'medium'     => self::CHANNEL_REFERRAL,
                'source'     => parse_url($referer, PHP_URL_HOST),
                'searchTerm' => '',
            ];
        } else {
            $userRefererCRM = [
                'medium'     => self::CHANNEL_UNKNOWN,
                'source'     => '',
                'searchTerm' => '',
            ];
        }

        $this->saveUserRefererCRM($userRefererCRM);
    }

    public function isInternalUrl($url)
    {
        return parse_url($url, PHP_URL_HOST) == Request::getDomain();
    }

    public static function getCrmUserReferer()
    {
        if (!empty(self::$userRefererCRM)) {
            return self::$userRefererCRM;
        } elseif (!empty($_COOKIE['userRefererCRM'])) {
            return json_decode(base64_decode($_COOKIE['userRefererCRM']), true);
        }

        return null;
    }

}