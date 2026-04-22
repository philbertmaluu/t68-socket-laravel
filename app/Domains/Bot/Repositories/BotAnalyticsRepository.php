<?php

namespace App\Domains\Bot\Repositories;

use Illuminate\Support\Facades\DB;

class BotAnalyticsRepository
{
    public function queueSnapshot(?string $officeId, ?int $tenantId): array
    {
        $tickets = DB::table('tickets');

        if ($officeId) {
            $tickets->where('office_id', $officeId);
        }
        if ($tenantId !== null) {
            $tickets->where('tenant_id', $tenantId);
        }

        $totalWaiting = (clone $tickets)->where('status', 'waiting')->count();
        $totalServing = (clone $tickets)->whereIn('status', ['called', 'serving'])->count();
        $completedToday = (clone $tickets)
            ->where('status', 'completed')
            ->whereDate('updated_at', now()->toDateString())
            ->count();

        $avgWaitSeconds = (clone $tickets)
            ->whereNotNull('called_at')
            ->whereNotNull('created_at')
            ->selectRaw('AVG((called_at - created_at) * 86400) as avg_wait_seconds')
            ->value('avg_wait_seconds');

        return [
            'office_id' => $officeId,
            'total_waiting' => (int) $totalWaiting,
            'total_serving' => (int) $totalServing,
            'completed_today' => (int) $completedToday,
            'avg_wait_minutes' => (int) round(((float) $avgWaitSeconds) / 60),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    public function waitTimeTrend(?string $officeId, ?int $tenantId, int $hours = 8): array
    {
        $startAt = now()->subHours(max(1, $hours));

        $query = DB::table('tickets')
            ->whereNotNull('called_at')
            ->whereNotNull('created_at')
            ->where('created_at', '>=', $startAt);

        if ($officeId) {
            $query->where('office_id', $officeId);
        }
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        $rows = $query
            ->selectRaw("TO_CHAR(created_at, 'YYYY-MM-DD HH24:00') as hour_bucket")
            ->selectRaw('COUNT(*) as tickets')
            ->selectRaw('AVG((called_at - created_at) * 1440) as avg_wait_minutes')
            ->groupByRaw("TO_CHAR(created_at, 'YYYY-MM-DD HH24:00')")
            ->orderByRaw("TO_CHAR(created_at, 'YYYY-MM-DD HH24:00') asc")
            ->get();

        return [
            'office_id' => $officeId,
            'window_hours' => $hours,
            'points' => $rows->map(fn ($row) => [
                'hour' => $row->hour_bucket,
                'tickets' => (int) $row->tickets,
                'avg_wait_minutes' => (int) round((float) $row->avg_wait_minutes),
            ])->values()->all(),
        ];
    }

    public function ticketContext(string $ticketNumber, ?int $tenantId): array
    {
        $query = DB::table('tickets as t')
            ->leftJoin('queues as q', 'q.id', '=', 't.queue_id')
            ->leftJoin('counters as c', 'c.id', '=', 't.counter_id')
            ->leftJoin('users as u', 'u.id', '=', 't.clerk_id')
            ->where('t.ticket_number', $ticketNumber);

        if ($tenantId !== null) {
            $query->where('t.tenant_id', $tenantId);
        }

        $ticket = $query
            ->select([
                't.id',
                't.ticket_number',
                't.status',
                't.service_type',
                't.office_id',
                't.created_at',
                't.called_at',
                't.completed_at',
                'q.name as queue_name',
                'q.status as queue_status',
                'c.name as counter_name',
                'u.name as clerk_name',
            ])
            ->first();

        if (!$ticket) {
            return [
                'ticket_number' => $ticketNumber,
                'found' => false,
            ];
        }

        return [
            'found' => true,
            'ticket' => [
                'id' => (string) $ticket->id,
                'ticket_number' => (string) $ticket->ticket_number,
                'status' => (string) $ticket->status,
                'service_type' => $ticket->service_type,
                'office_id' => $ticket->office_id,
                'queue_name' => $ticket->queue_name,
                'queue_status' => $ticket->queue_status,
                'counter_name' => $ticket->counter_name,
                'clerk_name' => $ticket->clerk_name,
                'created_at' => $ticket->created_at ? (string) $ticket->created_at : null,
                'called_at' => $ticket->called_at ? (string) $ticket->called_at : null,
                'completed_at' => $ticket->completed_at ? (string) $ticket->completed_at : null,
            ],
        ];
    }

    public function clerkWorkload(?string $officeId, ?int $tenantId, int $limit = 5): array
    {
        $query = DB::table('tickets as t')
            ->leftJoin('users as u', 'u.id', '=', 't.clerk_id')
            ->whereNotNull('t.clerk_id');

        if ($officeId) {
            $query->where('t.office_id', $officeId);
        }
        if ($tenantId !== null) {
            $query->where('t.tenant_id', $tenantId);
        }

        $rows = $query
            ->selectRaw('t.clerk_id')
            ->selectRaw('MAX(u.name) as clerk_name')
            ->selectRaw("SUM(CASE WHEN t.status = 'waiting' THEN 1 ELSE 0 END) as waiting_count")
            ->selectRaw("SUM(CASE WHEN t.status IN ('called','serving') THEN 1 ELSE 0 END) as in_progress_count")
            ->selectRaw("SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_count")
            ->groupBy('t.clerk_id')
            ->orderByDesc('in_progress_count')
            ->orderByDesc('waiting_count')
            ->limit(max(1, min(20, $limit)))
            ->get();

        return [
            'office_id' => $officeId,
            'items' => $rows->map(fn ($row) => [
                'clerk_id' => (string) $row->clerk_id,
                'clerk_name' => $row->clerk_name ?: 'Unknown',
                'waiting_count' => (int) $row->waiting_count,
                'in_progress_count' => (int) $row->in_progress_count,
                'completed_count' => (int) $row->completed_count,
            ])->values()->all(),
        ];
    }

    public function serviceRequirements(string $serviceId, ?int $tenantId): array
    {
        $serviceQuery = DB::table('services')->where('id', $serviceId);
        $documentsQuery = DB::table('service_documents')->where('service_id', $serviceId);

        if ($tenantId !== null) {
            $serviceQuery->where('tenant_id', $tenantId);
            $documentsQuery->where('tenant_id', $tenantId);
        }

        $service = $serviceQuery->first();
        if (!$service) {
            return [
                'service_id' => $serviceId,
                'found' => false,
            ];
        }

        $documents = $documentsQuery
            ->orderBy('order_index')
            ->get(['document_name', 'is_required', 'order_index']);

        return [
            'found' => true,
            'service' => [
                'id' => (string) $service->id,
                'name' => (string) $service->name,
                'estimated_time' => (int) ($service->estimated_time ?? 0),
            ],
            'documents' => $documents->map(fn ($doc) => [
                'name' => (string) $doc->document_name,
                'required' => (bool) $doc->is_required,
                'order' => (int) $doc->order_index,
            ])->values()->all(),
        ];
    }
}
