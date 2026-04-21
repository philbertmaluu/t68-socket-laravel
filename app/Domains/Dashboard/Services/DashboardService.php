<?php

namespace App\Domains\Dashboard\Services;

use App\Domains\Counter\Models\Counter;
use App\Domains\Authentication\Models\User;
use App\Domains\Dashboard\Enums\Dashboard;
use App\Domains\Ticket\Models\Ticket;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;

class DashboardService
{
    public function supervisorDashboard(): array
    {
        $user = Auth::guard('sanctum')->user();
        if (!$user) {
            throw new AuthenticationException('User not authenticated');
        }

        $officeId = $user->office_id ? (string) $user->office_id : null;

        $countersQuery = Counter::query();
        $ticketsQuery = Ticket::query();

        if ($officeId !== null && $officeId !== '') {
            $countersQuery->where('office_id', $officeId);
            $ticketsQuery->where('office_id', $officeId);
        }

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
