<?php


namespace Okay\Core\UserReferer;

use Okay\Core\Security\SessionNames;


use Okay\Core\Request;
use Snowplow\RefererParser\Config\JsonConfigReader;
use Snowplow\RefererParser\Parser;
use Snowplow\RefererParser\Referer;

class UserReferer
{
    
    const CHANNEL_EMAIL = 'email';
    const CHANNEL_SEARCH = 'search';
    const CHANNEL_SOCIAL = 'social';
    const CHANNEL_REFERRAL = 'referral';
    const CHANNEL_UNKNOWN = 'unknown';
    
    /** @var Parser */
    private $parser;
    
    private static $userReferer;
    
    public function __construct(Parser $parser)
    {
        $this->parser = $parser;
    }

    public function parse()
    {
        $userReferer = null;
        $referer = $this->parser->parse(
            Request::getReferer(),
            Request::getCurrentUrl()
        );

        if ($referer->isKnown()) {
            $medium = $referer->getMedium();

            // ok_orders.referer_channel - ENUM із п'яти значень, а довідник знає
            // ще paid і chatbot: усе поза трьома каналами лягає в referral.
            if (!in_array($medium, [self::CHANNEL_EMAIL, self::CHANNEL_SEARCH, self::CHANNEL_SOCIAL], true)) {
                $medium = self::CHANNEL_REFERRAL;
            }

            $userReferer = [
                'medium' => $medium,
                'source' => $referer->getSource(),
            ];
        } elseif (($referer = Request::getReferer()) && !$this->isInternalUrl($referer)) {
            $userReferer = [
                'medium' => self::CHANNEL_REFERRAL,
                'source' => parse_url($referer, PHP_URL_HOST),
            ];
        } else {
            $userReferer = [
                'medium' => self::CHANNEL_UNKNOWN,
                'source' => '',
            ];
        }
        
        $this->saveUserReferer($userReferer);
    }
    
    private function saveUserReferer(array $referer)
    {
        self::$userReferer = $referer;
        setcookie('userReferer', base64_encode(json_encode($referer)), [
            'expires'  => time() + 60 * 60 * 24 * 3,
            'path'     => '/',
            'secure'   => SessionNames::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    
    public function isInternalUrl($url)
    {
        return parse_url($url, PHP_URL_HOST) == Request::getDomain();
    }
    
    /**
     * Джерело переходу читається з куки відвідувача, тож пам'ять про
     * попереднього приписала б його трафік чужому.
     */
    public static function resetRequestState(): void
    {
        self::$userReferer = null;
    }

    public static function getUserReferer()
    {
        if (!empty(self::$userReferer)) {
            return self::$userReferer;
        } elseif (!empty($_COOKIE['userReferer'])) {
            return json_decode(base64_decode($_COOKIE['userReferer']), true);
        }
        
        return null;
    }
    
    public static function createConfigReader()
    {
        return new JsonConfigReader(__DIR__ . '/data/referers.json');
    }
}
