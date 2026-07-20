<?php

namespace Tests\Feature;

use App\Domains\Device\Models\Device;
use App\Domains\Mood\Models\MoodFeedbackSession;
use App\Domains\Mood\Models\MoodGeneralFeedback;
use App\Domains\Mood\Services\MoodAuthService;
use App\Domains\Mood\Services\MoodFeedbackSessionService;
use App\Domains\Ticket\Models\Ticket;
use App\Events\TicketCompleted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class MoodCheckerFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_mood_login_and_configuration(): void
    {
        $device = $this->createMoodDevice(Device::MOOD_MODE_GENERAL);

        $response = $this->postJson('/api/qms/mood/login', [
            'name' => $device->name,
            'password' => 'secret123',
            'device_uuid' => 'test-device-uuid-001',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.device.mode', Device::MOOD_MODE_GENERAL);

        $token = $response->json('data.access_token');
        $deviceUuid = $response->json('data.device.device_uuid');

        $this->getJson('/api/qms/mood/configuration', $this->moodHeaders($token, $deviceUuid))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'theme',
                    'messages',
                    'rating_options',
                    'feedback_reasons',
                ],
            ]);
    }

    public function test_general_feedback_submission(): void
    {
        $device = $this->createMoodDevice(Device::MOOD_MODE_GENERAL);
        $auth = $this->loginMoodDevice($device);

        $response = $this->postJson('/api/qms/mood/general-feedback', [
            'client_uuid' => (string) Str::uuid(),
            'rating' => 5,
        ], $this->moodHeaders($auth['token'], $auth['device_uuid']));

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('mood_general_feedback', [
            'device_id' => $device->id,
            'rating_score' => 5,
        ]);
    }

    public function test_counter_feedback_session_lifecycle(): void
    {
        $device = $this->createMoodDevice(Device::MOOD_MODE_COUNTER, counterId: 'counter-1');
        $ticket = $this->createTicket(counterId: 'counter-1');

        $session = (new MoodFeedbackSessionService())->createForTicket($ticket);
        $this->assertNotNull($session);
        $this->assertDatabaseHas('mood_feedback_sessions', [
            'id' => $session->id,
            'device_id' => $device->id,
            'status' => MoodFeedbackSession::STATUS_PENDING,
        ]);

        $token = $this->loginMoodDevice($device);
        $auth = $token;

        $pending = $this->getJson('/api/qms/mood/pending-session', $this->moodHeaders($auth['token'], $auth['device_uuid']));
        $pending->assertOk()->assertJsonPath('data.session_id', $session->id);

        $submit = $this->postJson('/api/qms/mood/counter-feedback', [
            'client_uuid' => (string) Str::uuid(),
            'session_id' => $session->id,
            'rating' => 4,
        ], $this->moodHeaders($auth['token'], $auth['device_uuid']));

        $submit->assertCreated();
        $this->assertDatabaseHas('mood_counter_feedback', [
            'session_id' => $session->id,
            'rating_score' => 4,
        ]);
        $this->assertDatabaseHas('mood_feedback_sessions', [
            'id' => $session->id,
            'status' => MoodFeedbackSession::STATUS_COMPLETED,
        ]);
    }

    public function test_session_expire_endpoint(): void
    {
        $device = $this->createMoodDevice(Device::MOOD_MODE_COUNTER, counterId: 'counter-2');
        $auth = $this->loginMoodDevice($device);

        $session = MoodFeedbackSession::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => 1,
            'ticket_id' => null,
            'counter_id' => 'counter-2',
            'officer_id' => null,
            'branch_id' => 'office-1',
            'device_id' => $device->id,
            'service_id' => null,
            'customer_type' => 'visitor',
            'start_time' => now(),
            'expire_time' => now()->addSeconds(30),
            'status' => MoodFeedbackSession::STATUS_PENDING,
        ]);

        $this->postJson('/api/qms/mood/session/expire', [
            'session_id' => $session->id,
        ], $this->moodHeaders($auth['token'], $auth['device_uuid']))
            ->assertOk()
            ->assertJsonPath('data.status', MoodFeedbackSession::STATUS_EXPIRED);
    }

    public function test_offline_sync_is_idempotent(): void
    {
        $device = $this->createMoodDevice(Device::MOOD_MODE_GENERAL);
        $auth = $this->loginMoodDevice($device);
        $clientUuid = (string) Str::uuid();

        $payload = [
            'items' => [[
                'client_uuid' => $clientUuid,
                'type' => 'general',
                'payload' => ['rating' => 3],
            ]],
        ];

        $first = $this->postJson('/api/qms/mood/offline-sync', $payload, $this->moodHeaders($auth['token'], $auth['device_uuid']));
        $first->assertOk()->assertJsonPath('data.processed', 1);

        $second = $this->postJson('/api/qms/mood/offline-sync', $payload, $this->moodHeaders($auth['token'], $auth['device_uuid']));
        $second->assertOk()->assertJsonPath('data.duplicates', 1);

        $this->assertEquals(1, MoodGeneralFeedback::where('client_uuid', $clientUuid)->count());
    }

    public function test_ticket_completed_creates_mood_session(): void
    {
        $device = $this->createMoodDevice(Device::MOOD_MODE_COUNTER, counterId: 'counter-3');
        $ticket = $this->createTicket(counterId: 'counter-3');

        event(new TicketCompleted($ticket));

        $this->assertDatabaseHas('mood_feedback_sessions', [
            'device_id' => $device->id,
            'ticket_id' => $ticket->id,
        ]);
    }

    private function seedTenant(): void
    {
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

    private function createMoodDevice(string $mode, ?string $counterId = null): Device
    {
        $this->seedTenant();

        $id = DB::table('devices')->insertGetId([
            'tenant_id' => 1,
            'name' => 'Mood Tablet '.Str::random(4),
            'type' => Device::TYPE_MOOD_CHECKER,
            'mood_mode' => $mode,
            'counter_id' => $counterId,
            'status' => Device::STATUS_OFFLINE,
            'region_id' => 'region-1',
            'office_id' => 'office-1',
            'serial_number' => 'MOOD-'.Str::upper(Str::random(8)),
            'password' => Crypt::encryptString('secret123'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Device::withoutGlobalScope('tenant')->findOrFail($id);
    }

    private function loginMoodDevice(Device $device): array
    {
        $uuid = 'uuid-'.Str::random(6);
        $result = (new MoodAuthService())->login([
            'name' => $device->name,
            'password' => 'secret123',
            'device_uuid' => $uuid,
        ]);

        return [
            'token' => $result['access_token'],
            'device_uuid' => $uuid,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function moodHeaders(string $token, ?string $deviceUuid = null): array
    {
        $headers = ['Authorization' => 'Bearer '.$token];
        if ($deviceUuid !== null) {
            $headers['X-Device-UUID'] = $deviceUuid;
        }

        return $headers;
    }

    private function createTicket(string $counterId): Ticket
    {
        $this->seedTenant();

        Schema::disableForeignKeyConstraints();

        $ticketId = DB::table('tickets')->insertGetId([
            'tenant_id' => 1,
            'ticket_number' => 'T'.random_int(1000, 9999),
            'service_type' => 'general',
            'queue_id' => 1,
            'status' => 'completed',
            'office_id' => 'office-1',
            'counter_id' => $counterId,
            'clerk_id' => 'clerk-1',
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::enableForeignKeyConstraints();

        return Ticket::withoutGlobalScope('tenant')->findOrFail($ticketId);
    }
}
