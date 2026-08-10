<?php


namespace Okay\Core;


/**
 * @deprecated Лишився заради зворотної сумісності з модулями. Нове йде через
 *             Okay\Core\SocialShare — назва JsSocial указувала на бібліотеку
 *             jssocials, якої у форку більше немає.
 */
class JsSocial
{
    private $socialShare;

    public function __construct(SocialShare $socialShare)
    {
        $this->socialShare = $socialShare;
    }

    public function getSocials()
    {
        return $this->socialShare->getNetworks();
    }

    /**
     * Доліплювало мережі, яких бракувало у вбудованому списку jssocials.
     * Тепер список повний, тож доліплювати нічого. Метод лишається, щоб
     * foreach у чужому шаблоні не впав.
     */
    public function getCustomSocials()
    {
        return [];
    }

    public static function getSocialDomain($link)
    {
        return SocialShare::getSocialDomain($link);
    }
}
