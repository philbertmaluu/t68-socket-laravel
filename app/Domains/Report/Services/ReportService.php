<?php

namespace App\Domains\Report\Services;

use App\Domains\Authentication\Models\User;
use App\Domains\Counter\Models\Counter;
use App\Domains\Ticket\Models\Ticket;
use App\Traits\UserOfficeTrait;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReportService
{
    use UserOfficeTrait;

    /**
     * Queue Summary report.
     *
     * @return array{reportData: array<string, mixed>, meta: array{officeId: string|null, from: string, to: string}}
     */
    public function queueSummary(string $from, string $to): array
    {
        [$base, $officeId] = $this->scopedTicketQuery($from, $to);

        $totalTickets = (clone $base)->count();
        $completedTickets = (clone $base)->where('status', 'completed')->count();
        $skippedTickets = (clone $base)->whereIn('status', ['skipped', 'no_show', 'cancelled'])->count();
        $transferredTickets = (clone $base)->whereNotNull('transferred_to_counter_id')->count();

        $hourlyData = $this->buildHourlyData(clone $base);
        $dailyTrends = $this->buildDailyTrends(clone $base);

        $peakHour = '—';
        if (!empty($hourlyData)) {
            $peak = collect($hourlyData)->sortByDesc('tickets')->first();
            $peakHour = (string) ($peak['hour'] ?? '—');
        }

        $busiestDay = '—';
        if (!empty($dailyTrends)) {
            $busy = collect($dailyTrends)->sortByDesc('tickets')->first();
            if (!empty($busy['date'])) {
                $busiestDay = \Carbon\Carbon::parse($busy['date'])->format('M d, Y');
            }
        }

        return [
            'reportData' => [
                'totalTickets' => $totalTickets,
                'completedTickets' => $completedTickets,
                'skippedTickets' => $skippedTickets,
                'transferredTickets' => $transferredTickets,
                'averageWaitTime' => $this->averageWaitMinutes(clone $base),
                'averageServiceTime' => $this->averageServiceMinutes(clone $base),
                'peakHour' => $peakHour,
                'busiestDay' => $busiestDay,
            ],
            'meta' => $this->meta($officeId, $from, $to),
        ];
    }

    /**
     * Daily Trends report.
     *
     * @return array{dailyTrends: list<array<string, mixed>>, meta: array{officeId: string|null, from: string, to: string}}
     */
    public function dailyTrends(string $from, string $to): array
    {
        [$base, $officeId] = $this->scopedTicketQuery($from, $to);

        return [
            'dailyTrends' => $this->buildDailyTrends(clone $base),
            'meta' => $this->meta($officeId, $from, $to),
        ];
    }

    /**
     * Service Mix report.
     *
     * @return array{serviceTypes: list<array<string, mixed>>, meta: array{officeId: string|null, from: string, to: string}}
     */
    public function serviceMix(string $from, string $to): array
    {
        [$base, $officeId] = $this->scopedTicketQuery($from, $to);
        $totalTickets = (clone $base)->count();

        return [
            'serviceTypes' => $this->buildServiceTypes(clone $base, $totalTickets),
            'meta' => $this->meta($officeId, $from, $to),
        ];
    }

    /**
     * Hourly Activity report.
     *
     * @return array{hourlyData: list<array<string, mixed>>, meta: array{officeId: string|null, from: string, to: string}}
     */
    public function hourlyActivity(string $from, string $to): array
    {
        [$base, $officeId] = $this->scopedTicketQuery($from, $to);

        return [
            'hourlyData' => $this->buildHourlyData(clone $base),
            'meta' => $this->meta($officeId, $from, $to),
        ];
    }

    /**
     * Counter Performance report.
     *
     * @return array{counterPerformance: list<array<string, mixed>>, meta: array{officeId: string|null, from: string, to: string}}
     */
    public function counterPerformance(string $from, string $to): array
    {
        [$base, $officeId] = $this->scopedTicketQuery($from, $to);

        return [
            'counterPerformance' => $this->buildCounterPerformance(clone $base, $officeId),
            'meta' => $this->meta($officeId, $from, $to),
        ];
    }

    /**
     * Clerk Performance report.
     *
     * @return array{clerkPerformance: list<array<string, mixed>>, meta: array{officeId: string|null, from: string, to: string}}
     */
    public function clerkPerformance(string $from, string $to): array
    {
        [$base, $officeId] = $this->scopedTicketQuery($from, $to);

        return [
            'clerkPerformance' => $this->buildClerkPerformance(clone $base),
            'meta' => $this->meta($officeId, $from, $to),
        ];
    }

    /**
     * Ticket rows for report drill-down (matches ServedTicket shape on the frontend).
     *
     * @param array{date?: string|null, hour?: string|null, serviceType?: string|null, counterName?: string|null, clerkName?: string|null, limit?: int|null} $filters
     * @return array{tickets: list<array<string, mixed>>, meta: array{total: int, from: string, to: string}}
     */
    public function tickets(string $from, string $to, array $filters = []): array
    {
        $this->requireAuth();
        $officeId = $this->currentOfficeId();

        $filterDate = trim((string) ($filters['date'] ?? ''));
        if ($filterDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDate)) {
            $start = $filterDate . ' 00:00:00';
            $end = $filterDate . ' 23:59:59';
        } else {
            $start = $from . ' 00:00:00';
            $end = $to . ' 23:59:59';
        }

        $query = Ticket::query()->whereBetween('created_at', [$start, $end]);

        if ($officeId !== '') {
            $query->where('office_id', $officeId);
        }

        $hour = trim((string) ($filters['hour'] ?? ''));
        if ($hour !== '' && preg_match('/^(\d{1,2})/', $hour, $m)) {
            $hourNum = (int) $m[1];
            $query->whereRaw("TO_NUMBER(TO_CHAR(created_at, 'HH24')) = ?", [$hourNum]);
        }

        $serviceType = trim((string) ($filters['serviceType'] ?? ''));
        if ($serviceType !== '') {
            $query->where('service_type', $serviceType);
        }

        $counterName = trim((string) ($filters['counterName'] ?? ''));
        if ($counterName !== '') {
            $counterId = Counter::query()->where('name', $counterName)->value('id');
            if ($counterId) {
                $query->where('counter_id', $counterId);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $clerkName = trim((string) ($filters['clerkName'] ?? ''));
        if ($clerkName !== '') {
            $clerkId = User::query()->where('name', $clerkName)->value('id');
            if ($clerkId) {
                $query->where('clerk_id', $clerkId);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $limit = (int) ($filters['limit'] ?? 500);
        if ($limit < 1) {
            $limit = 500;
        }
        $limit = min($limit, 1000);

        $tickets = $query
            ->orderBy('created_at')
            ->limit($limit)
            ->get([
                'id',
                'ticket_number',
                'service_type',
                'member_name',
                'member_number',
                'status',
                'counter_id',
                'clerk_id',
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
            'tickets' => $mapped,
            'meta' => [
                'total' => count($mapped),
                'from' => $from,
                'to' => $to,
            ],
        ];
    }

    /**
     * @return array{0: \Illuminate\Database\Eloquent\Builder, 1: string}
     */
    private function scopedTicketQuery(string $from, string $to): array
    {
        $this->requireAuth();
        $officeId = $this->currentOfficeId();

        $query = Ticket::query()->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59']);
        if ($officeId !== '') {
            $query->where('office_id', $officeId);
        }

        return [$query, $officeId];
    }

    private function requireAuth(): void
    {
        $user = Auth::guard('sanctum')->user();
        if (!$user) {
            throw new AuthenticationException('User not authenticated');
        }
    }

    private function currentOfficeId(): string
    {
        return trim((string) ($this->getUserOfficeAndRegionFromHrp()['office_id'] ?? ''));
    }

    /**
     * @return array{officeId: string|null, from: string, to: string}
     */
    private function meta(string $officeId, string $from, string $to): array
    {
        return [
            'officeId' => $officeId !== '' ? $officeId : null,
            'from' => $from,
            'to' => $to,
        ];
    }

    private function averageWaitMinutes($query): float
    {
        $rows = (clone $query)
            ->whereNotNull('created_at')
            ->whereNotNull('called_at')
            ->orderByDesc('created_at')
            ->limit(2000)
            ->get(['created_at', 'called_at']);

        if ($rows->isEmpty()) {
            return 0.0;
        }

        $total = 0.0;
        $count = 0;
        foreach ($rows as $row) {
            $created = $row->created_at;
            $called = $row->called_at;
            if (!$created || !$called) {
                continue;
            }
            $minutes = max(0, $created->diffInSeconds($called) / 60);
            $total += $minutes;
            $count++;
        }

        if ($count === 0) {
            return 0.0;
        }

        return round($total / $count, 1);
    }

    private function averageServiceMinutes($query): float
    {
        $avgSeconds = (clone $query)
            ->where('status', 'completed')
            ->whereNotNull('duration_seconds')
            ->where('duration_seconds', '>', 0)
            ->avg('duration_seconds');

        if ($avgSeconds === null) {
            return 0.0;
        }

        return round(((float) $avgSeconds) / 60, 1);
    }

    /**
     * @return list<array{date: string, tickets: int, completed: int, avgWaitTime: float}>
     */
    private function buildDailyTrends($query): array
    {
        $rows = (clone $query)
            ->selectRaw("TO_CHAR(created_at, 'YYYY-MM-DD') as activity_date, COUNT(*) as ticket_count, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count")
            ->groupByRaw("TO_CHAR(created_at, 'YYYY-MM-DD')")
            ->orderByRaw("TO_CHAR(created_at, 'YYYY-MM-DD') asc")
            ->get();

        $result = [];
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

            $dayQuery = (clone $query)->whereBetween('created_at', [$date . ' 00:00:00', $date . ' 23:59:59']);
            $result[] = [
                'date' => $date,
                'tickets' => (int) $row->ticket_count,
                'completed' => (int) $row->completed_count,
                'avgWaitTime' => $this->averageWaitMinutes($dayQuery),
            ];
        }

        return $result;
    }

    /**
     * @return list<array{type: string, count: int, percentage: float}>
     */
    private function buildServiceTypes($query, int $totalTickets): array
    {
        $rows = (clone $query)
            ->selectRaw('service_type, COUNT(*) as ticket_count')
            ->whereNotNull('service_type')
            ->groupBy('service_type')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $type = trim((string) ($row->service_type ?? ''));
            if ($type === '') {
                continue;
            }
            $count = (int) $row->ticket_count;
            $result[] = [
                'type' => $type,
                'count' => $count,
                'percentage' => $totalTickets > 0 ? round(($count / $totalTickets) * 100, 1) : 0,
            ];
        }

        return $result;
    }

    /**
     * @return list<array{hour: string, tickets: int, avgWaitTime: float}>
     */
    private function buildHourlyData($query): array
    {
        $rows = (clone $query)
            ->selectRaw("TO_CHAR(created_at, 'HH24') as activity_hour, COUNT(*) as ticket_count")
            ->groupByRaw("TO_CHAR(created_at, 'HH24')")
            ->orderByRaw("TO_CHAR(created_at, 'HH24') asc")
            ->get();

        $byHour = [];
        foreach ($rows as $row) {
            $hourRaw = trim((string) $row->activity_hour);
            $hourNum = (int) $hourRaw;
            $byHour[$hourNum] = (int) $row->ticket_count;
        }

        $result = [];
        for ($hour = 8; $hour <= 17; $hour++) {
            $hourStr = str_pad((string) $hour, 2, '0', STR_PAD_LEFT) . ':00';
            $hourQuery = (clone $query)->whereRaw("TO_NUMBER(TO_CHAR(created_at, 'HH24')) = ?", [$hour]);
            $result[] = [
                'hour' => $hourStr,
                'tickets' => (int) ($byHour[$hour] ?? 0),
                'avgWaitTime' => $this->averageWaitMinutes($hourQuery),
            ];
        }

        return $result;
    }

    /**
     * @return list<array{name: string, ticketsServed: int, avgServiceTime: float, efficiency: float}>
     */
    private function buildCounterPerformance($query, string $officeId): array
    {
        $rows = (clone $query)
            ->whereNotNull('counter_id')
            ->selectRaw('counter_id, COUNT(*) as served_count, AVG(CASE WHEN duration_seconds > 0 THEN duration_seconds ELSE NULL END) as avg_duration')
            ->groupBy('counter_id')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $counterIds = $rows->pluck('counter_id')->map(fn ($id) => (string) $id)->all();
        $names = Counter::query()->whereIn('id', $counterIds)->pluck('name', 'id');

        $result = [];
        foreach ($rows as $row) {
            $id = (string) $row->counter_id;
            $served = (int) $row->served_count;
            $avgMinutes = $row->avg_duration ? round(((float) $row->avg_duration) / 60, 1) : 0.0;
            $efficiency = $avgMinutes > 0 ? round($served / $avgMinutes, 1) : 0.0;

            $result[] = [
                'name' => (string) ($names->get($id) ?? 'Unknown'),
                'ticketsServed' => $served,
                'avgServiceTime' => $avgMinutes,
                'efficiency' => $efficiency,
            ];
        }

        usort($result, static fn ($a, $b) => $b['ticketsServed'] <=> $a['ticketsServed']);

        return $result;
    }

    /**
     * @return list<array{name: string, ticketsServed: int, avgServiceTime: float, completionRate: float}>
     */
    private function buildClerkPerformance($query): array
    {
        $rows = (clone $query)
            ->whereNotNull('clerk_id')
            ->selectRaw("clerk_id, COUNT(*) as served_count, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count, AVG(CASE WHEN duration_seconds > 0 THEN duration_seconds ELSE NULL END) as avg_duration")
            ->groupBy('clerk_id')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $clerkIds = $rows->pluck('clerk_id')->map(fn ($id) => (string) $id)->all();
        $names = User::query()->whereIn('id', $clerkIds)->pluck('name', 'id');

        $result = [];
        foreach ($rows as $row) {
            $id = (string) $row->clerk_id;
            $served = (int) $row->served_count;
            $completed = (int) $row->completed_count;
            $avgMinutes = $row->avg_duration ? round(((float) $row->avg_duration) / 60, 1) : 0.0;
            $completionRate = $served > 0 ? round(($completed / $served) * 100, 0) : 0;

            $result[] = [
                'name' => (string) ($names->get($id) ?? 'Unknown'),
                'ticketsServed' => $served,
                'avgServiceTime' => $avgMinutes,
                'completionRate' => $completionRate,
            ];
        }

        usort($result, static fn ($a, $b) => $b['ticketsServed'] <=> $a['ticketsServed']);

        return $result;
    }
}
