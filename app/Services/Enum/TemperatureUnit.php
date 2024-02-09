<?php

namespace App\Services\Enum;

enum TemperatureUnit: string
{
    use EnumFromName;

    case CELSIUS = '0';
    case FAHRENHEIT = '1';
}
