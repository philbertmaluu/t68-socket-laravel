<?php

namespace App\Domains\Dashboard\Services;

use App\Domains\Counter\Models\Counter;
use App\Domains\Authentication\Models\User;
use App\Domains\Dashboard\Enums\Dashboard;
use App\Domains\Ticket\Models\Ticket;
use App\Traits\UserOfficeTrait;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;

class DashboardService
{
    use UserOfficeTrait;

    public function supervisorDashboard(): array
    {
        $user = Auth::guard('sanctum')->user();
        if (!$user) {
            throw new AuthenticationException('User not authenticated');
        }

        $officeId = (string) $this->getUserOfficeAndRegionFromHrp()['office_id'];

        $countersQuery = Counter::query();
        $ticketsQuery = Ticket::query();

        $countersQuery->where('office_id', $officeId);
        $ticketsQuery->where('office_id', $officeId);

        $totalCounters = (clone $countersQuery)->count();
        $activeCounters = (clone $countersQuery)->where('status', 'ACTIVE')->count();
        $totalTicketsServed = (clone $ticketsQuery)->where('status', 'completed')->count();
        $totalCustomersWaiting = (clone $ticketsQuery)->where('status', 'waiting')->count();

        $avgWaitTime = $this->calculateAverageWaitTimeMinutes(clone $ticketsQuery);
        $counters = $this->buildSupervisorCounters(clone $countersQuery, $officeId);

        return [
            'stats' => [
                'totalCounters' => $totalCounters,
                'activeCounters' => $activeCounters,
                'totalTicketsServed' => $totalTicketsServed,
                'avgWaitTime' => $avgWaitTime,
                'totalCustomersWaiting' => $totalCustomersWaiting,
            ],
            // Keep these arrays in payload contract; frontend still uses local dummy data for now.
            'counters' => $counters,
            'contributionData' => [],
            'serviceTypeData' => [],
            'meta' => [
                'dashboard' => Dashboard::SUPERVISOR->value,
                'generatedAt' => now()->toIso8601String(),
                'source' => 'database',
                'officeId' => $officeId,
            ],
        ];
    }

    public function clerkDashboard(): array
    {
        return $this->buildPlaceholderPayload(Dashboard::CLERK);
    }

    public function adminDashboard(): array
    {
        return $this->buildPlaceholderPayload(Dashboard::ADMIN);
    }

    public function tenantDashboard(): array
    {
        return $this->buildPlaceholderPayload(Dashboard::TENANT);
    }

    /**
     * Tickets per office per date for the GitHub-style activity graph.
     *
     * @return array{
     *   year: int,
     *   days: list<array{date: string, count: int, level: int, offices: list<array{office_id: string, office_name: string|null, count: int}>}>,
     *   meta: array{officeId: string|null, generatedAt: string, source: string}
     * }
     */
    public function officeActivities(int $year): array
    {
        $user = Auth::guard('sanctum')->user();
        if (!$user) {
            throw new AuthenticationException('User not authenticated');
        }

        if ($year < 2000 || $year > 2100) {
            $year = (int) now()->year;
        }

        $location = $this->getUserOfficeAndRegionFromHrp();
        $officeId = trim((string) ($location['office_id'] ?? ''));
        $officeName = isset($location['office_name']) ? (string) $location['office_name'] : null;

        $query = Ticket::query()->whereNotNull('created_at');

        // Scope to the authenticated user's office (same as supervisor dashboard).
        if ($officeId !== '') {
            $query->where('office_id', $officeId);
        }

        // Oracle-compatible daily grouping (TO_CHAR, not MySQL DATE()).
        $yearStart = sprintf('%d-01-01 00:00:00', $year);
        $yearEnd = sprintf('%d-12-31 23:59:59', $year);

        $rows = $query
            ->whereBetween('created_at', [$yearStart, $yearEnd])
            ->selectRaw("TO_CHAR(created_at, 'YYYY-MM-DD') as activity_date, office_id, COUNT(*) as ticket_count")
            ->groupByRaw("TO_CHAR(created_at, 'YYYY-MM-DD'), office_id")
            ->orderByRaw("TO_CHAR(created_at, 'YYYY-MM-DD') asc")
            ->get();

        /** @var array<string, array<string, int>> $byDateOffice */
        $byDateOffice = [];
        foreach ($rows as $row) {
            $rawDate = $row->activity_date;
            if ($rawDate instanceof \DateTimeInterface) {
                $date = $rawDate->format('Y-m-d');
            } else {
                $date = substr(trim((string) $rawDate), 0, 10);
            }
            $rowOfficeId = (string) ($row->office_id ?? '');
            if ($date === '' || $rowOfficeId === '') {
                continue;
            }
            $byDateOffice[$date][$rowOfficeId] = (int) $row->ticket_count;
        }

        $officeNames = [];
        if ($officeId !== '') {
            $officeNames[$officeId] = $officeName;
        }

        // Fill any other office ids seen in the year with null names (HRP lookup is per-user).
        foreach ($byDateOffice as $officeCounts) {
            foreach (array_keys($officeCounts) as $id) {
                $id = (string) $id;
                if (!array_key_exists($id, $officeNames)) {
                    $officeNames[$id] = null;
                }
            }
        }

        $days = [];
        $start = new \DateTimeImmutable(sprintf('%d-01-01', $year));
        $end = new \DateTimeImmutable(sprintf('%d-12-31', $year));

        for ($date = $start; $date <= $end; $date = $date->modify('+1 day')) {
            $dateStr = $date->format('Y-m-d');
            $officeCounts = $byDateOffice[$dateStr] ?? [];
            $total = array_sum($officeCounts);

            $offices = [];
            foreach ($officeCounts as $id => $count) {
                $offices[] = [
                    'office_id' => (string) $id,
                    'office_name' => $officeNames[(string) $id] ?? null,
                    'count' => (int) $count,
                ];
            }

            // Keep stable order by office name then id
            usort($offices, static function (array $a, array $b): int {
                return strcmp(
                    (string) ($a['office_name'] ?? $a['office_id']),
                    (string) ($b['office_name'] ?? $b['office_id'])
                );
            });

            $days[] = [
                'date' => $dateStr,
                'count' => (int) $total,
                'level' => $this->activityLevel((int) $total),
                'offices' => $offices,
            ];
        }

        return [
            'year' => $year,
            'days' => $days,
            'meta' => [
                'officeId' => $officeId !== '' ? $officeId : null,
                'generatedAt' => now()->toIso8601String(),
                'source' => 'database',
            ],
        ];
    }

    /**
     * Tickets for a single day (matches the office-activities graph cell click).
     *
     * @return array{date: string, tickets: list<array<string, mixed>>, meta: array{officeId: string|null, total: int}}
     */
    public function officeActivityTickets(string $date): array
    {
        $user = Auth::guard('sanctum')->user();
        if (!$user) {
            throw new AuthenticationException('User not authenticated');
        }

        $location = $this->getUserOfficeAndRegionFromHrp();
        $officeId = trim((string) ($location['office_id'] ?? ''));

        $dayStart = $date . ' 00:00:00';
        $dayEnd = $date . ' 23:59:59';

        $query = Ticket::query()
            ->whereBetween('created_at', [$dayStart, $dayEnd]);

        if ($officeId !== '') {
            $query->where('office_id', $officeId);
        }

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
                'office_id',
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
                'officeId' => $officeId !== '' ? $officeId : null,
                'total' => count($mapped),
            ],
        ];
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

    private function buildPlaceholderPayload(Dashboard $dashboard): array
    {
        return [
            'dashboard' => $dashboard->value,
            'title' => $dashboard->label(),
            'generated_at' => now()->toIso8601String(),
            'summary' => [
                'total_tickets' => 0,
                'waiting_tickets' => 0,
                'served_tickets' => 0,
                'avg_wait_time_minutes' => 0,
            ],
            'charts' => [
                'tickets_by_hour' => [],
                'tickets_by_service' => [],
            ],
            'alerts' => [],
            'meta' => [
                'source' => 'placeholder',
                'notes' => 'Dashboard service scaffold is ready. Replace placeholder values with real aggregates.',
            ],
        ];
    }

    private function calculateAverageWaitTimeMinutes($ticketsQuery): int
    {
        $samples = $ticketsQuery
            ->whereNotNull('created_at')
            ->whereNotNull('called_at')
            ->orderByDesc('created_at')
            ->limit(500)
            ->get(['created_at', 'called_at']);

        if ($samples->isEmpty()) {
            return 0;
        }

        $totalMinutes = 0;
        $count = 0;

        foreach ($samples as $ticket) {
            if (!$ticket->created_at || !$ticket->called_at) {
                continue;
            }

            $diff = $ticket->created_at->diffInMinutes($ticket->called_at, false);
            if ($diff >= 0) {
                $totalMinutes += $diff;
                $count++;
            }
        }

        if ($count === 0) {
            return 0;
        }

        return (int) round($totalMinutes / $count);
    }

    private function buildSupervisorCounters($countersQuery, ?string $officeId): array
    {
        $counters = $countersQuery
            ->with([
                'counterType:id,name,code',
                'activeCounterClerks:id,counter_id,clerk_id,is_active,assigned_at',
            ])
            ->get(['id', 'name', 'status', 'counter_type_id', 'office_id']);

        if ($counters->isEmpty()) {
            return [];
        }

        $counterIds = $counters->pluck('id')->map(fn ($id) => (string) $id)->values()->all();

        $servedStatsQuery = Ticket::query();
        if ($officeId !== null && $officeId !== '') {
            $servedStatsQuery->where('office_id', $officeId);
        }

        $servedStats = $servedStatsQuery
            ->whereIn('counter_id', $counterIds)
            ->where('status', 'completed')
            ->selectRaw('counter_id, COUNT(*) as served_count, AVG(duration_seconds) as avg_duration')
            ->groupBy('counter_id')
            ->get()
            ->keyBy('counter_id');

        $currentTicketsQuery = Ticket::query();
        if ($officeId !== null && $officeId !== '') {
            $currentTicketsQuery->where('office_id', $officeId);
        }

        $currentTickets = $currentTicketsQuery
            ->whereIn('counter_id', $counterIds)
            ->whereIn('status', ['called', 'serving'])
            ->orderByDesc('called_at')
            ->orderByDesc('updated_at')
            ->get(['counter_id', 'ticket_number'])
            ->unique('counter_id')
            ->keyBy('counter_id');

        $clerkIds = $counters
            ->flatMap(fn ($counter) => $counter->activeCounterClerks->pluck('clerk_id'))
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();

        $clerkNames = empty($clerkIds)
            ? collect()
            : User::query()->whereIn('id', $clerkIds)->pluck('name', 'id');

        return $counters
            ->map(function ($counter) use ($servedStats, $currentTickets, $clerkNames) {
                $counterId = (string) $counter->id;
                $served = $servedStats->get($counterId);
                $ticket = $currentTickets->get($counterId);
                $activeClerkId = $counter->activeCounterClerks->first()?->clerk_id;

                return [
                    'id' => $counterId,
                    'name' => (string) $counter->name,
                    'type' => $this->resolveCounterType($counter->counterType?->code, $counter->counterType?->name),
                    'officer' => $activeClerkId ? ($clerkNames->get((string) $activeClerkId) ?? 'Unassigned') : 'Unassigned',
                    'status' => $this->mapCounterStatus((string) $counter->status, $ticket !== null),
                    'currentTicket' => $ticket?->ticket_number,
                    'ticketsServed' => (int) ($served?->served_count ?? 0),
                    'avgServiceTime' => (int) round((float) ($served?->avg_duration ?? 0)),
                ];
            })
            ->sortBy('name')
            ->values()
            ->all();
    }

    private function resolveCounterType(?string $counterTypeCode, ?string $counterTypeName): string
    {
        $rawType = $counterTypeCode ?: $counterTypeName ?: '';
        return strtolower(trim((string) $rawType));
    }

    private function mapCounterStatus(string $counterStatus, bool $hasCurrentTicket): string
    {
        $normalized = strtoupper(trim($counterStatus));
        if ($normalized !== 'ACTIVE') {
            return 'offline';
        }

        return $hasCurrentTicket ? 'busy' : 'available';
    }
}
