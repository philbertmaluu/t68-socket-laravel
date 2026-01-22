<?php

namespace App\Shared\Helpers;

class PaginationHelper
{
    public static function calculateMeta(int $total, int $perPage, int $currentPage): array
    {
        $lastPage = max(1, (int) ceil($total / $perPage));
        $currentPage = max(1, min($currentPage, $lastPage));

        return [
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $currentPage,
            'last_page' => $lastPage,
            'from' => $total > 0 ? (($currentPage - 1) * $perPage) + 1 : 0,
            'to' => min($currentPage * $perPage, $total),
        ];
    }

    public static function validateParams(int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        return [$page, $perPage];
    }
}
