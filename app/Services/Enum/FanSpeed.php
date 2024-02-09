<?php

namespace App\Services\Enum;

enum FanSpeed: string
{
    use EnumFromName;

    case Auto = '0';
    case SuperLow = '5';
    case Low = '6';
    case Medium = '7';
    case High = '8';
    case SuperHigh = '9';
}
