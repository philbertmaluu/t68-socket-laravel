<?php

namespace App\Shared\Helpers;

use Illuminate\Support\Str;

class SlugHelper
{
    public static function generate(string $text): string
    {
        return Str::slug($text);
    }

    public static function generateUnique(string $text, callable $existsCheck): string
    {
        $baseSlug = self::generate($text);
        $slug = $baseSlug;
        $counter = 1;

        while ($existsCheck($slug)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
