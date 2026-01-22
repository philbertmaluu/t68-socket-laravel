<?php

namespace App\Shared\Helpers;

use Illuminate\Support\Str;

class UuidHelper
{
    public static function generate(): string
    {
        return Str::uuid()->toString();
    }

    public static function generateShort(): string
    {
        return Str::random(8);
    }

    public static function generatePrefixed(string $prefix): string
    {
        return $prefix . '-' . self::generateShort();
    }

    public static function isValid(string $uuid): bool
    {
        return Str::isUuid($uuid);
    }
}
