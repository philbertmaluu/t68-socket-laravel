<?php

namespace Tests\Feature;

use App\Domains\Ticket\Models\TicketDailySequence;
use App\Domains\Ticket\Services\TicketNumberAllocator;
use App\Domains\Ticket\Services\TicketService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class DailyOfficeTicketNumberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        app()->instance('tenant.id', 1);
        Event::fake();

        if (!DB::table('tenants')->where('id', 1)->exists()) {
            DB::table('tenants')->insert([
                'id' => 1,
                'name' => 'Tenant A',
                'domain' => 'tenant-a.local',
                'database' => 'tenant_a',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function test_offices_share_a1_on_the_same_day_and_increment_locally(): void
    {
        $allocator = new TicketNumberAllocator();
        Carbon::setTestNow(Carbon::parse('2026-10-09 12:00:00', TicketNumberAllocator::BUSINESS_TIMEZONE));

        $hqFirst = $allocator->allocate('OFF-HQ', 1);
        $aruFirst = $allocator->allocate('OFF-ARU', 1);
        $hqSecond = $allocator->allocate('OFF-HQ', 1);

        $this->assertSame('A1', $hqFirst['ticket_number']);
        $this->assertSame('A1', $aruFirst['ticket_number']);
        $this->assertSame('A2', $hqSecond['ticket_number']);
        $this->assertSame('2026-10-09', $hqFirst['issued_on']);
    }

    public function test_sequence_resets_to_a1_on_the_next_tanzania_business_day(): void
    {
        $allocator = new TicketNumberAllocator();

        Carbon::setTestNow(Carbon::parse('2026-10-09 23:30:00', TicketNumberAllocator::BUSINESS_TIMEZONE));
        $this->assertSame('A1', $allocator->allocate('OFF-HQ', 1)['ticket_number']);
        $this->assertSame('A2', $allocator->allocate('OFF-HQ', 1)['ticket_number']);

        Carbon::setTestNow(Carbon::parse('2026-10-10 00:05:00', TicketNumberAllocator::BUSINESS_TIMEZONE));
        $nextDay = $allocator->allocate('OFF-HQ', 1);

        $this->assertSame('A1', $nextDay['ticket_number']);
        $this->assertSame('2026-10-10', $nextDay['issued_on']);
    }

    public function test_rolled_back_allocation_does_not_skip_a_number(): void
    {
        $allocator = new TicketNumberAllocator();
        Carbon::setTestNow(Carbon::parse('2026-10-09 10:00:00', TicketNumberAllocator::BUSINESS_TIMEZONE));

        DB::beginTransaction();
        $this->assertSame('A1', $allocator->allocate('OFF-HQ', 1)['ticket_number']);
        DB::rollBack();

        $this->assertSame(0, TicketDailySequence::query()->where('office_id', 'OFF-HQ')->count());
        $this->assertSame('A1', $allocator->allocate('OFF-HQ', 1)['ticket_number']);
    }

    public function test_unique_constraint_allows_same_number_on_two_offices_and_two_days(): void
    {
        $queueId = $this->seedQueue('OFF-HQ');
        $otherQueueId = $this->seedQueue('OFF-ARU', 'ARU-TYPE');

        $this->insertTicket($queueId, 'OFF-HQ', 'A1', '2026-10-09');
        $this->insertTicket($otherQueueId, 'OFF-ARU', 'A1', '2026-10-09');
        $this->insertTicket($queueId, 'OFF-HQ', 'A1', '2026-10-10');

        $this->assertDatabaseCount('tickets', 3);
    }

    public function test_create_ticket_uses_per_office_daily_sequence(): void
    {
        $hq = $this->seedOfficeForCreate('OFF-HQ', 'HQ-TYPE');
        $aru = $this->seedOfficeForCreate('OFF-ARU', 'ARU-TYPE');
        $service = new TicketService();

        Carbon::setTestNow(Carbon::parse('2026-10-09 09:00:00', TicketNumberAllocator::BUSINESS_TIMEZONE));

        $hqTicket = $service->createTicket([
            'service_type_id' => $hq['service_id'],
            'phone_number' => '255711000001',
            'office_id' => 'OFF-HQ',
        ]);
        $aruTicket = $service->createTicket([
            'service_type_id' => $aru['service_id'],
            'phone_number' => '255711000002',
            'office_id' => 'OFF-ARU',
        ]);
        $hqSecond = $service->createTicket([
            'service_type_id' => $hq['service_id'],
            'phone_number' => '255711000003',
            'office_id' => 'OFF-HQ',
        ]);

        $this->assertSame('A1', $hqTicket->ticket_number);
        $this->assertSame('A1', $aruTicket->ticket_number);
        $this->assertSame('A2', $hqSecond->ticket_number);
        $this->assertSame('2026-10-09', $hqTicket->issued_on?->toDateString());
    }

    /**
     * @return array{service_id: int}
     */
    private function seedOfficeForCreate(string $officeId, string $counterTypeCode): array
    {
        $serviceId = (int) DB::table('services')->insertGetId([
            'tenant_id' => 1,
            'name' => 'Registration '.$officeId,
            'description' => 'Test',
            'estimated_time' => 300,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('office_services')->insert([
            'tenant_id' => 1,
            'office_id' => $officeId,
            'service_id' => $serviceId,
            'service_name' => 'Registration '.$officeId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->seedQueue($officeId, $counterTypeCode);

        return ['service_id' => $serviceId];
    }

    private function seedQueue(string $officeId, string $counterTypeCode = 'HQ-TYPE'): int
    {
        $counterTypeId = (int) DB::table('counter_types')->insertGetId([
            'tenant_id' => 1,
            'name' => $counterTypeCode,
            'code' => $counterTypeCode,
            'description' => $counterTypeCode,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $counterId = (int) DB::table('counters')->insertGetId([
            'tenant_id' => 1,
            'name' => 'Counter '.$officeId,
            'counter_type_id' => $counterTypeId,
            'status' => 'ACTIVE',
            'office_id' => $officeId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('queues')->insertGetId([
            'counter_id' => $counterId,
            'name' => 'Queue '.$officeId,
            'status' => 'NORMAL',
            'members_waiting' => 0,
            'members_being_served' => 0,
            'average_wait_time' => 0,
            'office_id' => $officeId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertTicket(int $queueId, string $officeId, string $ticketNumber, string $issuedOn): void
    {
        DB::table('tickets')->insert([
            'tenant_id' => 1,
            'ticket_number' => $ticketNumber,
            'issued_on' => $issuedOn,
            'service_type' => 'Registration',
            'service_id' => null,
            'queue_id' => $queueId,
            'priority' => 0,
            'status' => 'waiting',
            'office_id' => $officeId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
