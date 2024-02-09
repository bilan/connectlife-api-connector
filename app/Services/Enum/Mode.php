<?php

namespace App\Services\Enum;

enum Mode: string
{
    use EnumFromName;

    case off = 'off';
    case fan_only = '0';
    case heat = '1';
    case cool = '2';
    case dry = '3';
    case auto = '4';
}
