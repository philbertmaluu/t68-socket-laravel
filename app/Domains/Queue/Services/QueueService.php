<?php

namespace App\Domains\Queue\Services;

use App\Domains\Queue\Models\Queue;
use Illuminate\Support\Collection;

class QueueService
{
    /**
     * Get queues, optionally filtered by office_id.
     * - If office_id is provided: returns queues for that office.
     * - If office_id is omitted: returns queues grouped per office.
     */
    public function getAllQueuesPerOffice(?string $officeId = null): array
    {
        $todayDate = now()->toDateString();

        $queueRows = Queue::query()
            ->with([
                'counter:id,name,status',
                'counter.services:id,name',
                'tickets' => fn ($query) => $query
                    ->whereDate('created_at', $todayDate)
                    ->select(['id', 'queue_id', 'ticket_number', 'status', 'service_type', 'created_at']),
                'waitingTickets' => fn ($query) => $query
                    ->whereDate('created_at', $todayDate)
                    ->select(['id', 'queue_id', 'ticket_number', 'status', 'service_type', 'created_at']),
            ])
            ->withCount([
                'tickets as members_waiting_today_count' => fn ($query) => $query
                    ->where('status', 'waiting')
                    ->whereDate('created_at', $todayDate),
                'tickets as members_served_today_count' => fn ($query) => $query
                    ->where('status', '!=', 'waiting')
                    ->whereDate('created_at', $todayDate),
            ])
            ->when($officeId, fn ($query) => $query->where('office_id', $officeId))
            ->orderBy('office_id')
            ->orderBy('name')
            ->get();

        $queues = $queueRows->map(function (Queue $queue) {
            $counter = $queue->counter;
            $counterId = $counter ? (string) $counter->id : '';
            $counterStatus = strtoupper((string) ($counter?->status ?? ''));

            $serviceTypes = $counter
                ? $counter->services
                    ->filter(function ($service) use ($queue) {
                        $pivotOfficeId = (string) ($service->pivot?->office_id ?? '');
                        if ($pivotOfficeId === '') {
                            return true;
                        }

                        return $pivotOfficeId === (string) $queue->office_id;
                    })
                    ->pluck('name')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all()
                : [];

            return [
                'id' => (string) $queue->id,
                'name' => (string) $queue->name,
                'status' => $this->normalizeQueueStatus((string) $queue->status),
                'members_waiting' => (int) ($queue->members_waiting_today_count ?? 0),
                'members_being_served' => (int) ($queue->members_served_today_count ?? 0),
                'average_wait_time' => (int) $queue->average_wait_time,
                'office_id' => (string) $queue->office_id,
                'counter' => [
                    'id' => $counterId,
                    'name' => (string) ($counter?->name ?? ''),
                    'status' => $counterStatus,
                ],
                'all_tickets' => $queue->tickets->values()->all(),
                'waiting_tickets' => $queue->waitingTickets->values()->all(),
                'service_types' => $serviceTypes,
                // Helpful for frontend.
                'counters' => 1,
                'active_counters' => $counterStatus === 'ACTIVE' ? 1 : 0,
                'created_at' => $queue->created_at,
                'updated_at' => $queue->updated_at,
            ];
        })->values();

        if ($officeId) {
            return [
                'office_id' => $officeId,
                'total_queues' => $queues->count(),
                'queues' => $queues->all(),
            ];
        }

        $offices = $queues
            ->groupBy('office_id')
            ->map(function (Collection $officeQueues, string $id) {
                return [
                    'office_id' => $id,
                    'total_queues' => $officeQueues->count(),
                    'queues' => $officeQueues->values()->all(),
                ];
            })
            ->values()
            ->all();

        return [
            'total_offices' => count($offices),
            'total_queues' => $queues->count(),
            'offices' => $offices,
        ];
    }

    private function normalizeQueueStatus(string $status): string
    {
        $normalized = strtolower(trim($status));

        return match ($normalized) {
            'busy' => 'busy',
            'critical' => 'critical',
            'normal', 'free' => 'normal',
            default => 'normal',
        };
    }
}

