<?php


namespace Okay\Core\TplMod;


enum CheckStatus
{
    case Ok;
    case Multiple;
    case NoAnchor;
    case ChainBroken;
    case FileMissing;

    public static function isFailureOf(CheckStatus $status): bool
    {
        return $status !== self::Ok && $status !== self::Multiple;
    }
}
