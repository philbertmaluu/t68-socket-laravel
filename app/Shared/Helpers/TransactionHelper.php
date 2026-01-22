<?php

namespace App\Shared\Helpers;

use Closure;
use Illuminate\Support\Facades\DB;

class TransactionHelper
{
    public static function execute(Closure $callback): mixed
    {
        return DB::transaction($callback);
    }

    public static function executeWithRetry(Closure $callback, int $maxRetries = 3): mixed
    {
        $attempts = 0;

        while ($attempts < $maxRetries) {
            try {
                return self::execute($callback);
            } catch (\Exception $e) {
                $attempts++;

                if ($attempts >= $maxRetries) {
                    throw $e;
                }
            }
        }
    }
}
