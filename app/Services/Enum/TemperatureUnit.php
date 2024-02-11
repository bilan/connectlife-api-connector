<?php

namespace App\Services\Enum;

enum TemperatureUnit: string
{
    use EnumFromName;

    case celsius = '0';
    case fahrenheit = '1';
}
