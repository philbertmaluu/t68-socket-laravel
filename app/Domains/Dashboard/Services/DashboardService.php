<?php

namespace App\Domains\Dashboard\Services;

use App\Domains\Counter\Models\Counter;
use App\Domains\Authentication\Models\User;
use App\Domains\Dashboard\Enums\Dashboard;
use App\Domains\Device\Models\Device;
use App\Domains\Ticket\Models\Ticket;
use App\Traits\UserOfficeTrait;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        $user = Auth::guard('sanctum')->user();
        if (!$user) {
            throw new AuthenticationException('User not authenticated');
        }

        [$hrpRegions, $hrpOffices] = $this->loadHrpRegionsAndOffices();

        $ticketsByOffice = $this->adminTicketsByOffice();
        $waitByOffice = $this->adminWaitMinutesByOffice();
        $countersByOffice = $this->adminCountersByOffice();
        $devicesByOffice = $this->adminDevicesByOffice();

        $officesByRegion = [];
        foreach ($hrpOffices as $office) {
            $officesByRegion[(string) $office['region_id']][] = $office;
        }

        $regions = [];
        foreach ($hrpRegions as $region) {
            $regionId = (string) $region['id'];
            $regionOffices = $officesByRegion[$regionId] ?? [];
            $mappedOffices = [];
            foreach ($regionOffices as $office) {
                $officeKey = $this->officeKey($office['id']);
                $mappedOffices[] = $this->mapAdminOffice(
                    $office,
                    $ticketsByOffice[$officeKey] ?? ['waiting' => 0, 'serving' => 0, 'queues' => 0],
                    $waitByOffice[$officeKey] ?? 0,
                    $countersByOffice[$officeKey] ?? ['total' => 0, 'open' => 0],
                    $devicesByOffice[$officeKey] ?? [
                        'kiosk' => ['total' => 0, 'online' => 0],
                        'tv' => ['total' => 0, 'online' => 0],
                        'desktop' => ['total' => 0, 'online' => 0],
                    ],
                );
            }

            $regions[] = $this->mapAdminRegion($region, $mappedOffices);
        }

        return [
            'regions' => $regions,
            'meta' => [
                'dashboard' => Dashboard::ADMIN->value,
                'generatedAt' => now()->toIso8601String(),
                'source' => $hrpRegions === [] ? 'qms-without-hrp' : 'database',
            ],
        ];
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
     * Tickets for a day or date range (office-scoped).
     *
     * @return array{date: string|null, from: string|null, to: string|null, tickets: list<array<string, mixed>>, meta: array{officeId: string|null, officeName: string|null, total: int}}
     */
    public function officeActivityTickets(
        ?string $date = null,
        ?string $from = null,
        ?string $to = null
    ): array {
        $user = Auth::guard('sanctum')->user();
        if (!$user) {
            throw new AuthenticationException('User not authenticated');
        }

        $location = $this->getUserOfficeAndRegionFromHrp();
        $officeId = trim((string) ($location['office_id'] ?? ''));
        $officeName = isset($location['office_name']) ? (string) $location['office_name'] : null;

        if ($from && $to) {
            $dayStart = $from . ' 00:00:00';
            $dayEnd = $to . ' 23:59:59';
        } else {
            $date = $date ?: now()->toDateString();
            $dayStart = $date . ' 00:00:00';
            $dayEnd = $date . ' 23:59:59';
            $from = null;
            $to = null;
        }

        $query = Ticket::query()->whereBetween('created_at', [$dayStart, $dayEnd]);

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
            'date' => $from && $to ? null : $date,
            'from' => $from,
            'to' => $to,
            'tickets' => $mapped,
            'meta' => [
                'officeId' => $officeId !== '' ? $officeId : null,
                'officeName' => $officeName,
                'total' => count($mapped),
            ],
        ];
    }

    /**
     * Export office tickets as PDF or Excel for a day or date range.
     *
     * @return array{format: string, filename: string, content: string, mime: string}
     */
    public function exportOfficeActivityTickets(
        string $format,
        ?string $date = null,
        ?string $from = null,
        ?string $to = null
    ): array {
        $payload = $this->officeActivityTickets($date, $from, $to);
        $tickets = $payload['tickets'];
        $officeId = $payload['meta']['officeId'] ?? null;
        $officeName = $payload['meta']['officeName'] ?? null;

        if (!$officeName && $officeId) {
            $named = $this->withHrpOfficeNames(collect([[
                'office_id' => (string) $officeId,
                'office_name' => null,
            ]]));
            $officeName = $named->first()['office_name'] ?? null;
        }

        $services = collect($tickets)
            ->pluck('serviceType')
            ->filter(fn ($name) => is_string($name) && trim($name) !== '')
            ->unique()
            ->values()
            ->all();

        $counters = collect($tickets)
            ->pluck('counterName')
            ->filter(fn ($name) => is_string($name) && trim($name) !== '' && $name !== 'N/A')
            ->unique()
            ->values()
            ->all();

        $user = Auth::guard('sanctum')->user();
        $generatedBy = trim((string) ($user?->name ?? $user?->email ?? 'System'));
        if ($generatedBy === '') {
            $generatedBy = 'System';
        }

        $safeOffice = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($officeName ?: 'office')) ?: 'office';
        $isRange = $from && $to;
        if ($isRange) {
            $reportDate = \Carbon\Carbon::parse($from)->format('F j, Y')
                . ' – '
                . \Carbon\Carbon::parse($to)->format('F j, Y');
            $dateKey = str_replace('-', '', $from) . '-' . str_replace('-', '', $to);
        } else {
            $date = $date ?: now()->toDateString();
            $reportDate = \Carbon\Carbon::parse($date)->format('F j, Y');
            $dateKey = str_replace('-', '', $date);
        }

        if ($format === 'excel') {
            $csv = $this->buildOfficeTicketsCsv($tickets);
            return [
                'format' => 'excel',
                'filename' => "office-activity-{$safeOffice}-{$dateKey}.csv",
                'content' => $csv,
                'mime' => 'text/csv; charset=UTF-8',
            ];
        }

        $logoFile = public_path('images/nssf-logo.png');
        $logoPath = null;
        if (is_file($logoFile)) {
            $logoPath = 'data:image/png;base64,' . base64_encode((string) file_get_contents($logoFile));
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.office.activity-report', [
            'logoPath' => $logoPath,
            'officeName' => $officeName ?: 'N/A',
            'services' => $services,
            'counters' => $counters,
            'tickets' => $tickets,
            'reportDate' => $reportDate,
            'dateKey' => $dateKey,
            'generatedAt' => now()->format('d M Y H:i'),
            'generatedBy' => $generatedBy,
        ])
            ->setPaper('a4', 'landscape')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'DejaVu Sans',
                'dpi' => 96,
            ]);

        return [
            'format' => 'pdf',
            'filename' => "office-activity-{$safeOffice}-{$dateKey}.pdf",
            'content' => $pdf->output(),
            'mime' => 'application/pdf',
        ];
    }

    /**
     * @param list<array<string, mixed>> $tickets
     */
    private function buildOfficeTicketsCsv(array $tickets): string
    {
        $handle = fopen('php://temp', 'r+');
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

    /**
     * @return array{0: list<array{id: string, name: string}>, 1: list<array{id: string, name: string, region_id: string, office_code: string|null}>}
     */
    private function loadHrpRegionsAndOffices(): array
    {
        try {
            $regions = DB::table('hrpd.region')
                ->select(['region_id as id', 'region_name as name'])
                ->orderBy('region_name')
                ->get()
                ->map(fn ($row) => [
                    'id' => (string) $row->id,
                    'name' => (string) $row->name,
                ])
                ->values()
                ->all();

            $offices = DB::table('hrpd.office')
                ->select(['office_id as id', 'office_name as name', 'region_id', 'office_code'])
                ->orderBy('office_name')
                ->get()
                ->map(fn ($row) => [
                    'id' => (string) $row->id,
                    'name' => (string) $row->name,
                    'region_id' => (string) $row->region_id,
                    'office_code' => $row->office_code !== null ? (string) $row->office_code : null,
                ])
                ->values()
                ->all();

            return [$regions, $offices];
        } catch (\Throwable $e) {
            Log::warning('Admin dashboard HRP lookup failed', ['error' => $e->getMessage()]);

            return [[], []];
        }
    }

    /**
     * @return array<string, array{waiting: int, serving: int, queues: int}>
     */
    private function adminTicketsByOffice(): array
    {
        $rows = Ticket::query()
            ->whereIn('status', ['waiting', 'called', 'serving', 'paused'])
            ->get(['office_id', 'status', 'service_type']);

        $out = [];
        $queueKeys = [];
        foreach ($rows as $row) {
            $officeId = $this->officeKey($row->office_id ?? '');
            if ($officeId === '') {
                continue;
            }
            if (!isset($out[$officeId])) {
                $out[$officeId] = ['waiting' => 0, 'serving' => 0, 'queues' => 0];
            }
            $status = strtolower((string) $row->status);
            if ($status === 'waiting') {
                $out[$officeId]['waiting']++;
            }
            if (in_array($status, ['called', 'serving', 'paused'], true)) {
                $out[$officeId]['serving']++;
            }
            $service = trim((string) ($row->service_type ?? ''));
            if ($service !== '' && $status === 'waiting') {
                $queueKeys[$officeId][$service] = true;
            }
        }

        foreach ($queueKeys as $officeId => $services) {
            $out[$officeId]['queues'] = count($services);
        }
        foreach ($out as $officeId => $counts) {
            if ($counts['queues'] === 0 && $counts['waiting'] > 0) {
                $out[$officeId]['queues'] = 1;
            }
        }

        return $out;
    }

    /**
     * @return array<string, int>
     */
    private function adminWaitMinutesByOffice(): array
    {
        $samples = Ticket::query()
            ->whereNotNull('created_at')
            ->whereNotNull('called_at')
            ->where('created_at', '>=', now()->startOfDay())
            ->get(['office_id', 'created_at', 'called_at']);

        $totals = [];
        $counts = [];
        foreach ($samples as $ticket) {
            $officeId = $this->officeKey($ticket->office_id ?? '');
            if ($officeId === '' || !$ticket->created_at || !$ticket->called_at) {
                continue;
            }
            $diff = $ticket->created_at->diffInMinutes($ticket->called_at, false);
            if ($diff < 0) {
                continue;
            }
            $totals[$officeId] = ($totals[$officeId] ?? 0) + $diff;
            $counts[$officeId] = ($counts[$officeId] ?? 0) + 1;
        }

        $averages = [];
        foreach ($totals as $officeId => $total) {
            $averages[$officeId] = (int) round($total / max(1, $counts[$officeId] ?? 1));
        }

        return $averages;
    }

    /**
     * @return array<string, array{total: int, open: int}>
     */
    private function adminCountersByOffice(): array
    {
        $rows = Counter::query()->get(['office_id', 'status']);

        $out = [];
        foreach ($rows as $row) {
            $officeId = $this->officeKey($row->office_id ?? '');
            if ($officeId === '') {
                continue;
            }
            if (!isset($out[$officeId])) {
                $out[$officeId] = ['total' => 0, 'open' => 0];
            }
            $out[$officeId]['total']++;
            if (strtoupper((string) $row->status) === 'ACTIVE') {
                $out[$officeId]['open']++;
            }
        }

        return $out;
    }

    /**
     * @return array<string, array{kiosk: array{total: int, online: int}, tv: array{total: int, online: int}, desktop: array{total: int, online: int}>}
     */
    private function adminDevicesByOffice(): array
    {
        $rows = Device::query()
            ->whereIn('type', [Device::TYPE_KIOSK, Device::TYPE_TV, 'DESKTOP', 'desktop'])
            ->get(['office_id', 'type', 'status', 'last_seen']);

        $onlineCutoff = now()->subMinutes(10);
        $out = [];
        foreach ($rows as $row) {
            $officeId = $this->officeKey($row->office_id ?? '');
            if ($officeId === '') {
                continue;
            }
            if (!isset($out[$officeId])) {
                $out[$officeId] = [
                    'kiosk' => ['total' => 0, 'online' => 0],
                    'tv' => ['total' => 0, 'online' => 0],
                    'desktop' => ['total' => 0, 'online' => 0],
                ];
            }
            $type = strtoupper(str_replace('-', '_', (string) $row->type));
            $bucket = match ($type) {
                Device::TYPE_TV, 'TV' => 'tv',
                'DESKTOP' => 'desktop',
                default => 'kiosk',
            };
            $out[$officeId][$bucket]['total']++;
            if ($this->deviceIsOnline($row, $onlineCutoff)) {
                $out[$officeId][$bucket]['online']++;
            }
        }

        return $out;
    }

    private function deviceIsOnline(Device $device, \DateTimeInterface $onlineCutoff): bool
    {
        if ($device->last_seen instanceof \DateTimeInterface) {
            return $device->last_seen->gte($onlineCutoff);
        }

        return strtolower((string) $device->status) === Device::STATUS_ONLINE;
    }

    private function officeKey(mixed $id): string
    {
        $value = trim((string) $id);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^-?\d+(\.0+)?$/', $value) === 1) {
            return (string) (int) $value;
        }

        return $value;
    }

    /**
     * @param  array{id: string, name: string, region_id: string, office_code: string|null}  $office
     * @param  array{waiting: int, serving: int, queues: int}  $tickets
     * @param  array{total: int, open: int}  $counters
     * @param  array{kiosk: array{total: int, online: int}, tv: array{total: int, online: int}, desktop?: array{total: int, online: int}}  $devices
     * @return array<string, mixed>
     */
    private function mapAdminOffice(array $office, array $tickets, int $avgWait, array $counters, array $devices): array
    {
        $desktop = $devices['desktop'] ?? ['total' => 0, 'online' => 0];
        if ($desktop['total'] === 0 && $counters['total'] > 0) {
            $desktop = ['total' => $counters['total'], 'online' => $counters['open']];
        }
        $kioskOffline = max(0, $devices['kiosk']['total'] - $devices['kiosk']['online']);
        $tvOffline = max(0, $devices['tv']['total'] - $devices['tv']['online']);
        $desktopOffline = max(0, $desktop['total'] - $desktop['online']);

        return [
            'id' => $office['id'],
            'name' => $office['name'],
            'address' => $office['office_code'] ?: $office['name'],
            'activeQueues' => $tickets['queues'],
            'openCounters' => $counters['open'],
            'averageWaitingTime' => $avgWait,
            'membersWaiting' => $tickets['waiting'],
            'membersBeingServed' => $tickets['serving'],
            'deviceStatus' => [
                'kioskMachines' => [
                    'total' => $devices['kiosk']['total'],
                    'online' => $devices['kiosk']['online'],
                    'offline' => $kioskOffline,
                ],
                'tvDisplays' => [
                    'total' => $devices['tv']['total'],
                    'online' => $devices['tv']['online'],
                    'offline' => $tvOffline,
                ],
                'desktopApps' => [
                    'total' => $desktop['total'],
                    'online' => $desktop['online'],
                    'offline' => $desktopOffline,
                ],
            ],
        ];
    }

    /**
     * @param  array{id: string, name: string}  $region
     * @param  list<array<string, mixed>>  $offices
     * @return array<string, mixed>
     */
    private function mapAdminRegion(array $region, array $offices): array
    {
        $waiting = 0;
        $serving = 0;
        $queues = 0;
        $openCounters = 0;
        $waitWeighted = 0;
        $waitOffices = 0;
        $kiosk = ['total' => 0, 'online' => 0, 'offline' => 0];
        $tv = ['total' => 0, 'online' => 0, 'offline' => 0];
        $desktop = ['total' => 0, 'online' => 0, 'offline' => 0];

        foreach ($offices as $office) {
            $waiting += (int) $office['membersWaiting'];
            $serving += (int) $office['membersBeingServed'];
            $queues += (int) $office['activeQueues'];
            $openCounters += (int) $office['openCounters'];
            if ((int) $office['averageWaitingTime'] > 0) {
                $waitWeighted += (int) $office['averageWaitingTime'];
                $waitOffices++;
            }
            foreach (['kioskMachines' => &$kiosk, 'tvDisplays' => &$tv, 'desktopApps' => &$desktop] as $key => &$bucket) {
                $bucket['total'] += (int) $office['deviceStatus'][$key]['total'];
                $bucket['online'] += (int) $office['deviceStatus'][$key]['online'];
                $bucket['offline'] += (int) $office['deviceStatus'][$key]['offline'];
            }
            unset($bucket);
        }

        $avgWait = $waitOffices > 0 ? (int) round($waitWeighted / $waitOffices) : 0;

        return [
            'id' => $region['id'],
            'slug' => $this->regionMapSlug((string) $region['name']),
            'name' => $region['name'],
            'status' => $this->queueLoadStatus($waiting, $avgWait),
            'activeQueues' => $queues,
            'totalOffices' => count($offices),
            'openCounters' => $openCounters,
            'averageWaitingTime' => $avgWait,
            'totalMembersWaiting' => $waiting,
            'membersBeingServed' => $serving,
            'deviceStatus' => [
                'kioskMachines' => $kiosk,
                'tvDisplays' => $tv,
                'desktopApps' => $desktop,
            ],
            'offices' => $offices,
        ];
    }

    private function queueLoadStatus(int $waiting, int $avgWaitMinutes): string
    {
        if ($waiting >= 40 || $avgWaitMinutes >= 20) {
            return 'high';
        }
        if ($waiting >= 10 || $avgWaitMinutes >= 10) {
            return 'medium';
        }

        return 'low';
    }

    private function regionMapSlug(string $name): string
    {
        $normalized = strtolower(trim(preg_replace('/\s+/', ' ', $name) ?? $name));
        $aliases = [
            'dar es salaam' => 'dar-es-salaam',
            'dar-es-salaam' => 'dar-es-salaam',
            'coast' => 'pwani',
            'pwani region' => 'pwani',
            'mjini magharibi' => 'zanzibar',
            'unguja' => 'zanzibar',
            'zanzibar urban/west' => 'zanzibar',
            'zanzibar urban' => 'zanzibar',
            'kaskazini unguja' => 'kaskazini-unguja',
            'kusini unguja' => 'kusini-unguja',
            'kaskazini pemba' => 'kaskazini-pemba',
            'kusini pemba' => 'kusini-pemba',
        ];

        return $aliases[$normalized] ?? trim((string) preg_replace('/[^a-z0-9]+/', '-', $normalized), '-');
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
                $officerNames = $counter->activeCounterClerks
                    ->pluck('clerk_id')
                    ->filter()
                    ->map(fn ($clerkId) => $clerkNames->get((string) $clerkId))
                    ->filter()
                    ->unique()
                    ->values();

                return [
                    'id' => $counterId,
                    'name' => (string) $counter->name,
                    'type' => $this->resolveCounterType($counter->counterType?->code, $counter->counterType?->name),
                    'officer' => $officerNames->isNotEmpty() ? $officerNames->implode(', ') : 'Unassigned',
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
