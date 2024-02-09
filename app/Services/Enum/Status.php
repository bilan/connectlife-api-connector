<?php

namespace App\Services\Enum;

enum Status: string
{
    use EnumFromName;

    case ONLINE = 'Online';
    case OFFLINE = 'Offline';
}
