<?php

namespace Database\Seeders;

use App\Domains\Ticket\Models\Ticket;
use App\Domains\Ticket\Services\TicketNumberAllocator;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PreviousDayTicketsSeeder extends Seeder
{
    private const OFFICE_ID = '42';

    private const COUNTER_TYPE_CODE = 'SEED-PREV';

    public function run(): void
    {
        if (!Schema::hasColumn('tickets', 'issued_on')) {
            $this->command?->error('tickets.issued_on is missing. Run the issued_on migration first.');

            return;
        }

        $tenantId = DB::table('tenants')->orderBy('id')->value('id');
        if (!$tenantId) {
            $this->command?->error('No tenant found. Run TenantSeeder first.');

            return;
        }

        $queueId = $this->ensureQueue((int) $tenantId);
        $now = Carbon::now(TicketNumberAllocator::BUSINESS_TIMEZONE);
        $yesterday = $now->copy()->subDay();
        $twoDaysAgo = $now->copy()->subDays(2);

        Ticket::withoutTenant()
            ->where('ticket_number', 'like', 'SEED-PREV-%')
            ->forceDelete();

        $rows = [
            $this->row('SEED-PREV-WAITING', 'waiting', $yesterday, $queueId, $tenantId),
            $this->row('SEED-PREV-CALLED', 'called', $yesterday, $queueId, $tenantId, withCall: true),
            $this->row('SEED-PREV-SERVING', 'serving', $yesterday, $queueId, $tenantId, withCall: true, withServe: true),
            $this->row('SEED-PREV-PAUSED', 'paused', $yesterday, $queueId, $tenantId, withCall: true, withServe: true),
            $this->row('SEED-PREV-HOLD', 'hold', $yesterday, $queueId, $tenantId, withCall: true),
            $this->row('SEED-PREV-SUSPENDED', 'suspended', $yesterday, $queueId, $tenantId, withCall: true),
            $this->row('SEED-PREV-NULL-ISSUED', 'waiting', $twoDaysAgo, $queueId, $tenantId, issuedOn: null),
            $this->row('SEED-PREV-DONE', 'completed', $yesterday, $queueId, $tenantId, withCall: true, withServe: true),
            $this->row('SEED-PREV-CANCELLED', 'cancelled', $yesterday, $queueId, $tenantId),
            $this->row('SEED-PREV-TODAY', 'waiting', $now, $queueId, $tenantId),
        ];

        foreach ($rows as $row) {
            Ticket::withoutEvents(function () use ($row) {
                Ticket::withoutTenant()->create($row);
            });
        }

        $this->command?->info('Seeded '.count($rows).' tickets on office '.self::OFFICE_ID.'.');
        $this->command?->line('Open previous-day tickets the command should close: SEED-PREV-WAITING, CALLED, SERVING, PAUSED, HOLD, SUSPENDED, NULL-ISSUED.');
        $this->command?->line('Left alone: SEED-PREV-DONE, SEED-PREV-CANCELLED, SEED-PREV-TODAY.');
    }

    private function ensureQueue(int $tenantId): int
    {
        $existing = DB::table('queues')->where('office_id', self::OFFICE_ID)->value('id');
        if ($existing) {
            return (int) $existing;
        }

        $counterTypeId = DB::table('counter_types')->where('code', self::COUNTER_TYPE_CODE)->value('id');
        if (!$counterTypeId) {
            $counterTypeId = DB::table('counter_types')->insertGetId([
                'tenant_id' => $tenantId,
                'name' => 'Seed previous-day counter',
                'code' => self::COUNTER_TYPE_CODE,
                'description' => 'Fixture for tickets:complete-previous-days',
                'status' => 'ACTIVE',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $counterId = DB::table('counters')->where('office_id', self::OFFICE_ID)->value('id');
        if (!$counterId) {
            $counter = [
                'tenant_id' => $tenantId,
                'name' => 'Seed previous-day counter',
                'counter_type_id' => $counterTypeId,
                'status' => 'ACTIVE',
                'office_id' => self::OFFICE_ID,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if (Schema::hasColumn('counters', 'service_id')) {
                $serviceId = DB::table('services')->orderBy('id')->value('id');
                $counter['service_id'] = $serviceId;
            }
            $counterId = DB::table('counters')->insertGetId($counter);
        }

        return (int) DB::table('queues')->insertGetId([
            'counter_id' => $counterId,
            'name' => 'Seed previous-day queue',
            'status' => 'NORMAL',
            'members_waiting' => 0,
            'members_being_served' => 0,
            'average_wait_time' => 0,
            'office_id' => self::OFFICE_ID,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(
        string $ticketNumber,
        string $status,
        Carbon $businessTime,
        int $queueId,
        int $tenantId,
        bool $withCall = false,
        bool $withServe = false,
        ?string $issuedOn = 'auto',
    ): array {
        $at = $businessTime->copy()->timezone(TicketNumberAllocator::BUSINESS_TIMEZONE)->setTime(10, 0);
        $createdAt = $at->copy()->utc();
        $calledAt = $withCall ? $createdAt->copy()->addMinutes(5) : null;
        $servingAt = $withServe ? $createdAt->copy()->addMinutes(10) : null;

        return [
            'ticket_number' => $ticketNumber,
            'tenant_id' => $tenantId,
            'service_type' => 'Seed previous day',
            'queue_id' => $queueId,
            'member_name' => 'Seed Member',
            'member_number' => 'SEED-001',
            'phone_number' => null,
            'estimated_time' => 300,
            'priority' => false,
            'status' => $status,
            'office_id' => self::OFFICE_ID,
            'issued_on' => $issuedOn === 'auto' ? $at->toDateString() : $issuedOn,
            'queue_position' => 1,
            'called_at' => $calledAt,
            'serving_started_at' => $servingAt,
            'completed_at' => $status === 'completed' ? $createdAt->copy()->addMinutes(20) : null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];
    }
}
