<?php


namespace Okay\Core\TplMod;


enum CheckStatus
{
    case Ok;
    case Multiple;
    case NoAnchor;
    case ChainBroken;
    case FileMissing;

    /**
     * Ні $this, ні self у сигнатурі навмисно: PHPCompatibility 9.3.5 не розбирає enum
     * і бачить їх як ужиток поза класом, а phpcs гейтить CI.
     */
    public static function isFailureOf(CheckStatus $status): bool
    {
        return $status !== self::Ok && $status !== self::Multiple;
    }
}
