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
     * Раніше доліплювало odnoklassniki, якого не було у вбудованому списку
     * jssocials. Тепер ОК є у SocialShare нарівні з рештою, тож доліплювати
     * нічого. Метод лишається, щоб foreach у чужому шаблоні не впав.
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
