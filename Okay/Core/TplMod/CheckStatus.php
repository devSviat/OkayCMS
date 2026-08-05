<?php


namespace Okay\Core\TplMod;


enum CheckStatus
{
    case Ok;
    case Multiple;
    case NoAnchor;
    case ChainBroken;
    case FileMissing;

    public function isFailure(): bool
    {
        return match ($this) {
            self::Ok, self::Multiple => false,
            default => true,
        };
    }
}
