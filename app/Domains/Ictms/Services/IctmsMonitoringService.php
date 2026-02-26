<?php

namespace App\Domains\Ictms\Services;

use App\Domains\Ticket\Models\Ticket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class IctmsMonitoringService
{
    /**
     * Service health for GET /api/ictms/service.
     * Returns: [ { name, type, status, message } ], status 1 = healthy, 0 = down.
     */
    public function getServiceStatus(): array
    {
        $data = [];

        // Queue Management API (app)
        $data[] = [
            'name' => 'Queue Management API',
            'type' => 'Internal',
            'status' => 1,
            'message' => 'Listening...',
        ];

        // Database
        try {
            DB::connection()->getPdo();
            $data[] = [
                'name' => 'QMS',
                'type' => 'Internal',
                'status' => 1,
                'message' => 'Connected',
            ];
        } catch (\Throwable $e) {
            $data[] = [
                'name' => 'QMS',
                'type' => 'Internal',
                'status' => 0,
                'message' => $e->getMessage(),
            ];
        }

        // Redis (optional)
        try {
            $redisEnabled = config('database.redis.default');
            if ($redisEnabled) {
                Redis::ping();
                $data[] = [
                    'name' => 'Redis',
                    'type' => 'Internal',
                    'status' => 1,
                    'message' => 'Connected',
                ];
            }
        } catch (\Throwable $e) {
            $data[] = [
                'name' => 'Redis',
                'type' => 'Internal',
                'status' => 0,
                'message' => $e->getMessage(),
            ];
        }

        return $data;
    }

    /**
     * Interface status for GET /api/ictms/interface.
     * Returns: [ { name, total, overdue, error, status } ], status 1 = healthy, 0 = problem.
     */
    public function getInterfaceStatus(): array
    {
        $today = now()->startOfDay();

        // Ticket creation - tickets created today
        $ticketCreatedTotal = Ticket::withoutTenant()->where('created_at', '>=', $today)->count();
        $data[] = [
            'name' => 'Ticket Creation',
            'total' => $ticketCreatedTotal,
            'overdue' => '0',
            'error' => 0,
            'status' => 1,
        ];

        // SMS notification - no log table; report healthy with zero counts
        $data[] = [
            'name' => 'SMS Notification',
            'total' => 0,
            'overdue' => '0',
            'error' => 0,
            'status' => 1,
        ];

        // Counter calls - tickets completed today
        $counterCallsTotal = Ticket::withoutTenant()
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $today)
            ->count();
        $data[] = [
            'name' => 'Counter Calls',
            'total' => $counterCallsTotal,
            'overdue' => '0',
            'error' => 0,
            'status' => 1,
        ];

        return $data;
    }
}
