<?php

namespace App\Services\Enum;

trait EnumFromName
{
    public static function fromName(string $name): self
    {
        return constant("self::$name");
    }
}
