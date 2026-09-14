<?php

declare(strict_types=1);

namespace App\Domains\SelfService\Support;

/**
 * Accepts Tanzanian mobiles: 2557XXXXXXXX, 07XXXXXXXX, 06XXXXXXXX.
 * Stores and sends as local 0XXXXXXXXX.
 */
final class TanzaniaPhone
{
    public static function firstValid(string ...$candidates): ?string
    {
        foreach ($candidates as $candidate) {
            $normalized = self::normalize($candidate);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    public static function normalize(string $raw): ?string
    {
        $digits = preg_replace('/\D/', '', $raw) ?? '';
        if ($digits === '') {
            return null;
        }

        if (preg_match('/^255([67]\d{8})$/', $digits, $matches) === 1) {
            return '0'.$matches[1];
        }

        if (preg_match('/^0[67]\d{8}$/', $digits) === 1) {
            return $digits;
        }

        return null;
    }
}
