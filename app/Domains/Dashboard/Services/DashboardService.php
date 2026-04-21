<?php

namespace App\Domains\Dashboard\Services;

use App\Domains\Counter\Models\Counter;
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

        return [
            'stats' => [
                'totalCounters' => $totalCounters,
                'activeCounters' => $activeCounters,
                'totalTicketsServed' => $totalTicketsServed,
                'avgWaitTime' => $avgWaitTime,
                'totalCustomersWaiting' => $totalCustomersWaiting,
            ],
            // Keep these arrays in payload contract; frontend still uses local dummy data for now.
            'counters' => [],
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
}
