<?php

declare(strict_types=1);

namespace App\Domains\SelfService\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

final class SelfServiceLog
{
    public static function step(string $step, array $context = []): void
    {
        Log::info('[SelfService] '.$step, $context);
    }

    public static function warning(string $step, array $context = []): void
    {
        Log::warning('[SelfService] '.$step, $context);
    }

    public static function error(string $step, Throwable $e, array $context = []): void
    {
        Log::error('[SelfService] '.$step, array_merge($context, [
            'error' => $e->getMessage(),
            'exception' => $e::class,
        ]));
    }
}
