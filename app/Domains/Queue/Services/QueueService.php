<?php

namespace App\Domains\Queue\Services;

use App\Domains\Authentication\Models\User;
use App\Domains\Counter\Models\Counter;
use App\Domains\Queue\Models\Queue;
use App\Domains\Ticket\Models\Ticket;
use App\Traits\UserOfficeTrait;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class QueueService
{
    use UserOfficeTrait;

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

            $serviceTypes = [];
            if ($counter) {
                // Load service names directly from COUNTER_SERVICES by counter_id.
                // This avoids relation scope issues and guarantees counter-linked services are returned.
                $serviceTypes = DB::table('counter_services as cs')
                    ->join('services as s', 's.id', '=', 'cs.service_id')
                    ->where('cs.counter_id', $counter->id)
                    ->pluck('s.name')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
            }

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

        $queues = $this->withHrpOfficeNames($queues);
        $officeNames = $queues
            ->filter(fn ($queue) => !empty($queue['office_id']) && !empty($queue['office_name']))
            ->mapWithKeys(fn ($queue) => [(string) $queue['office_id'] => (string) $queue['office_name']])
            ->all();

        if ($officeId) {
            return [
                'office_id' => $officeId,
                'office_name' => $officeNames[$officeId] ?? null,
                'total_queues' => $queues->count(),
                'queues' => $queues->all(),
            ];
        }

        $offices = $queues
            ->groupBy('office_id')
            ->map(function (Collection $officeQueues, string $id) use ($officeNames) {
                return [
                    'office_id' => $id,
                    'office_name' => $officeNames[$id] ?? null,
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

    /**
     * GitHub-style heatmap: tickets per day for a queue's counter (counter = queue).
     *
     * @return array{year: int, days: list<array{date: string, count: int, level: int}>, meta: array{queueId: string, counterId: string|null, generatedAt: string}}
     */
    public function queueActivities(string $queueId, int $year): array
    {
        $queue = $this->findQueueForCurrentOffice($queueId);

        if ($year < 2000 || $year > 2100) {
            $year = (int) now()->year;
        }

        $counterId = $queue->counter_id ? (string) $queue->counter_id : null;
        $yearStart = sprintf('%d-01-01 00:00:00', $year);
        $yearEnd = sprintf('%d-12-31 23:59:59', $year);

        $query = Ticket::query()
            ->whereNotNull('created_at')
            ->whereBetween('created_at', [$yearStart, $yearEnd]);

        $this->scopeTicketsToQueueCounter($query, (string) $queue->id, $counterId);

        $rows = $query
            ->selectRaw("TO_CHAR(created_at, 'YYYY-MM-DD') as activity_date, COUNT(*) as ticket_count")
            ->groupByRaw("TO_CHAR(created_at, 'YYYY-MM-DD')")
            ->orderByRaw("TO_CHAR(created_at, 'YYYY-MM-DD') asc")
            ->get();

        /** @var array<string, int> $byDate */
        $byDate = [];
        foreach ($rows as $row) {
            $rawDate = $row->activity_date;
            if ($rawDate instanceof \DateTimeInterface) {
                $date = $rawDate->format('Y-m-d');
            } else {
                $date = substr(trim((string) $rawDate), 0, 10);
            }
            if ($date === '') {
                continue;
            }
            $byDate[$date] = (int) $row->ticket_count;
        }

        $days = [];
        $start = new \DateTimeImmutable(sprintf('%d-01-01', $year));
        $end = new \DateTimeImmutable(sprintf('%d-12-31', $year));

        for ($date = $start; $date <= $end; $date = $date->modify('+1 day')) {
            $dateStr = $date->format('Y-m-d');
            $count = (int) ($byDate[$dateStr] ?? 0);
            $days[] = [
                'date' => $dateStr,
                'count' => $count,
                'level' => $this->activityLevel($count),
            ];
        }

        return [
            'year' => $year,
            'days' => $days,
            'meta' => [
                'queueId' => (string) $queue->id,
                'counterId' => $counterId,
                'generatedAt' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * Tickets for a single day on a queue's counter (drawer when clicking the activity graph).
     *
     * @return array{date: string, tickets: list<array<string, mixed>>, meta: array{queueId: string, counterId: string|null, total: int}}
     */
    public function queueActivityTickets(string $queueId, string $date): array
    {
        $queue = $this->findQueueForCurrentOffice($queueId);
        $counterId = $queue->counter_id ? (string) $queue->counter_id : null;

        $dayStart = $date . ' 00:00:00';
        $dayEnd = $date . ' 23:59:59';

        $query = Ticket::query()->whereBetween('created_at', [$dayStart, $dayEnd]);
        $this->scopeTicketsToQueueCounter($query, (string) $queue->id, $counterId);

        $tickets = $query
            ->orderBy('created_at')
            ->get([
                'id',
                'ticket_number',
                'service_type',
                'member_name',
                'member_number',
                'status',
                'counter_id',
                'clerk_id',
                'queue_id',
                'created_at',
                'completed_at',
                'duration_seconds',
            ]);

        $counterIds = $tickets->pluck('counter_id')->filter()->map(fn ($id) => (string) $id)->unique()->values()->all();
        $clerkIds = $tickets->pluck('clerk_id')->filter()->map(fn ($id) => (string) $id)->unique()->values()->all();

        $counterNames = empty($counterIds)
            ? collect()
            : Counter::query()->whereIn('id', $counterIds)->pluck('name', 'id');

        $clerkNames = empty($clerkIds)
            ? collect()
            : User::query()->whereIn('id', $clerkIds)->pluck('name', 'id');

        $mapped = $tickets->map(function (Ticket $ticket) use ($counterNames, $clerkNames) {
            $durationSeconds = (int) ($ticket->duration_seconds ?? 0);
            $completedAt = $ticket->completed_at;
            $createdAt = $ticket->created_at;

            return [
                'id' => (string) $ticket->id,
                'ticketNumber' => (string) $ticket->ticket_number,
                'serviceType' => (string) ($ticket->service_type ?? ''),
                'memberName' => $ticket->member_name ? (string) $ticket->member_name : null,
                'memberNumber' => $ticket->member_number ? (string) $ticket->member_number : null,
                'status' => (string) ($ticket->status ?? ''),
                'counterName' => $ticket->counter_id
                    ? ($counterNames->get((string) $ticket->counter_id) ?? 'N/A')
                    : 'N/A',
                'clerkName' => $ticket->clerk_id
                    ? ($clerkNames->get((string) $ticket->clerk_id) ?? 'Unassigned')
                    : 'Unassigned',
                'completedAt' => $completedAt ? $completedAt->toIso8601String() : null,
                'createdAt' => $createdAt ? $createdAt->toIso8601String() : null,
                'durationMinutes' => $durationSeconds > 0 ? (int) round($durationSeconds / 60) : 0,
            ];
        })->values()->all();

        return [
            'date' => $date,
            'tickets' => $mapped,
            'meta' => [
                'queueId' => (string) $queue->id,
                'counterId' => $counterId,
                'total' => count($mapped),
            ],
        ];
    }

    /**
     * Export queue day tickets as PDF or Excel (CSV row data).
     *
     * @return array{format: string, filename: string, content: string, mime: string}
     */
    public function exportQueueActivityTickets(string $queueId, string $date, string $format): array
    {
        $queue = $this->findQueueForCurrentOffice($queueId);
        $payload = $this->queueActivityTickets($queueId, $date);
        $tickets = $payload['tickets'];

        $counterId = $queue->counter_id ? (string) $queue->counter_id : null;
        $counterName = '';
        if ($counterId) {
            $counterName = (string) (Counter::query()->where('id', $counterId)->value('name') ?? '');
        }

        $services = [];
        if ($counterId) {
            $services = DB::table('counter_services as cs')
                ->join('services as s', 's.id', '=', 'cs.service_id')
                ->where('cs.counter_id', $counterId)
                ->pluck('s.name')
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        $officeName = null;
        $queuesWithNames = $this->withHrpOfficeNames(collect([[
            'office_id' => (string) $queue->office_id,
            'office_name' => null,
        ]]));
        $officeName = $queuesWithNames->first()['office_name'] ?? null;

        $safeQueue = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $queue->name) ?: 'queue';
        $dateKey = str_replace('-', '', $date);
        $user = \Illuminate\Support\Facades\Auth::guard('sanctum')->user();
        $generatedBy = trim((string) ($user?->name ?? $user?->email ?? 'System'));
        if ($generatedBy === '') {
            $generatedBy = 'System';
        }

        if ($format === 'excel') {
            $csv = $this->buildTicketsCsv($tickets);
            return [
                'format' => 'excel',
                'filename' => "queue-activity-{$safeQueue}-{$dateKey}.csv",
                'content' => $csv,
                'mime' => 'text/csv; charset=UTF-8',
            ];
        }

        $logoFile = public_path('images/nssf-logo.png');
        $logoPath = is_file($logoFile) ? ('file://' . $logoFile) : null;

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.queue.activity-report', [
            'logoPath' => $logoPath,
            'queueName' => (string) $queue->name,
            'counterName' => $counterName,
            'officeName' => $officeName,
            'services' => $services,
            'tickets' => $tickets,
            'reportDate' => \Carbon\Carbon::parse($date)->format('F j, Y'),
            'dateKey' => $dateKey,
            'generatedAt' => now()->format('d M Y H:i'),
            'generatedBy' => $generatedBy !== '' ? $generatedBy : 'System',
        ])->setPaper('a4', 'landscape');

        return [
            'format' => 'pdf',
            'filename' => "queue-activity-{$safeQueue}-{$dateKey}.pdf",
            'content' => $pdf->output(),
            'mime' => 'application/pdf',
        ];
    }

    /**
     * @param list<array<string, mixed>> $tickets
     */
    private function buildTicketsCsv(array $tickets): string
    {
        $handle = fopen('php://temp', 'r+');
        // UTF-8 BOM so Excel opens accents correctly
        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, [
            'Ticket',
            'Service',
            'Member Name',
            'Member Number',
            'Status',
            'Created At',
            'Completed At',
            'Duration (min)',
            'Clerk',
            'Counter',
        ]);

        foreach ($tickets as $ticket) {
            fputcsv($handle, [
                $ticket['ticketNumber'] ?? '',
                $ticket['serviceType'] ?? '',
                $ticket['memberName'] ?? '',
                $ticket['memberNumber'] ?? '',
                $ticket['status'] ?? '',
                $ticket['createdAt'] ?? '',
                $ticket['completedAt'] ?? '',
                $ticket['durationMinutes'] ?? 0,
                $ticket['clerkName'] ?? '',
                $ticket['counterName'] ?? '',
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $csv;
    }

    private function findQueueForCurrentOffice(string $queueId): Queue
    {
        $officeId = trim((string) ($this->getUserOfficeAndRegionFromHrp()['office_id'] ?? ''));

        $query = Queue::query()->where('id', $queueId);
        if ($officeId !== '') {
            $query->where('office_id', $officeId);
        }

        $queue = $query->first();
        if (!$queue) {
            throw new NotFoundHttpException('Queue not found');
        }

        return $queue;
    }

    /**
     * Prefer counter_id (counter = queue); fall back to queue_id.
     */
    private function scopeTicketsToQueueCounter($query, string $queueId, ?string $counterId): void
    {
        if ($counterId !== null && $counterId !== '') {
            $query->where('counter_id', $counterId);
            return;
        }

        $query->where('queue_id', $queueId);
    }

    private function activityLevel(int $count): int
    {
        if ($count <= 0) {
            return 0;
        }
        if ($count <= 30) {
            return 1;
        }
        if ($count <= 60) {
            return 2;
        }
        if ($count <= 100) {
            return 3;
        }

        return 4;
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
