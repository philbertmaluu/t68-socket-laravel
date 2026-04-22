<?php

namespace Tests\Feature;

use App\Domains\Authentication\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BotChatFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.openai.api_key', 'test-api-key');
        config()->set('services.openai.model', 'gpt-4o-mini');
        Http::preventStrayRequests();
    }

    public function test_supervisor_receives_data_grounded_response_with_tool_calls(): void
    {
        $this->seedMinimumAccessData();
        $user = $this->createUserWithRole('ROLE_SUPERVISOR');
        $this->seedQueueData((int) $user->tenant_id, 'OFF1');

        Sanctum::actingAs($user);

        Http::fake([
            'https://api.openai.com/v1/chat/completions*' => Http::sequence()
                ->push([
                    'choices' => [[
                        'message' => [
                            'role' => 'assistant',
                            'content' => null,
                            'tool_calls' => [[
                                'id' => 'call_1',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'queue_snapshot',
                                    'arguments' => json_encode(['office_id' => 'OFF1']),
                                ],
                            ]],
                        ],
                    ]],
                    'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 20, 'total_tokens' => 120],
                ], 200)
                ->push([
                    'choices' => [[
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Queue is stable. Keep current staffing and monitor wait time every 30 minutes.',
                        ],
                    ]],
                    'usage' => ['prompt_tokens' => 120, 'completion_tokens' => 30, 'total_tokens' => 150],
                ], 200),
        ]);

        $response = $this->postJson('/api/qms/bot/chat', [
            'message' => 'Give me queue status and recommendation',
            'context' => ['office_id' => 'OFF1'],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.role_mode', 'supervisor')
            ->assertJsonPath('data.tool_calls.0.tool', 'queue_snapshot');

        Http::assertSentCount(2);
        $this->assertDatabaseCount('bot_conversations', 1);
        $this->assertDatabaseCount('bot_tool_calls', 1);
    }

    public function test_clerk_cannot_query_other_office_via_tool_call(): void
    {
        $this->seedMinimumAccessData();
        $user = $this->createUserWithRole('ROLE_CLERK');
        $this->seedClerkAssignment((int) $user->id, (int) $user->tenant_id, 'OFF1');

        Sanctum::actingAs($user);

        Http::fake([
            'https://api.openai.com/v1/chat/completions*' => Http::sequence()->push([
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [[
                            'id' => 'call_2',
                            'type' => 'function',
                            'function' => [
                                'name' => 'queue_snapshot',
                                'arguments' => json_encode(['office_id' => 'OFF2']),
                            ],
                        ]],
                    ],
                ]],
            ], 200),
        ]);

        $response = $this->postJson('/api/qms/bot/chat', [
            'message' => 'Show queue for office OFF2',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false);

        Http::assertSentCount(1);
    }

    private function seedMinimumAccessData(): void
    {
        DB::table('tenants')->insert([
            'id' => 1,
            'name' => 'Tenant A',
            'domain' => 'tenant-a.local',
            'database' => 'tenant_a',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('modules')->insert([
            'id' => 9001,
            'module_id' => 'QMS',
            'code' => 'QMS',
            'name' => 'Queue Management',
            'description' => 'QMS',
            'is_active' => 1,
            'created_by' => 1,
            'updated_by' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createUserWithRole(string $roleCode): User
    {
        $user = User::create([
            'tenant_id' => 1,
            'user_id' => 'PF' . fake()->numerify('#####'),
            'user_type' => 'staff',
            'name' => fake()->unique()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $roleId = DB::table('roles')->insertGetId([
            'module_id' => 9001,
            'role_code' => $roleCode,
            'role_name' => $roleCode,
            'created_by' => 1,
            'updated_by' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('user_roles')->insert([
            'user_id' => $user->id,
            'role_id' => $roleId,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDays(2),
            'status' => 'active',
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    private function seedQueueData(int $tenantId, string $officeId): void
    {
        $counterTypeId = DB::table('counter_types')->insertGetId([
            'tenant_id' => $tenantId,
            'name' => 'Benefits',
            'code' => 'BENEFITS',
            'description' => 'Benefits Counter',
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $counterId = DB::table('counters')->insertGetId([
            'tenant_id' => $tenantId,
            'name' => 'Counter 1',
            'counter_type_id' => $counterTypeId,
            'status' => 'ACTIVE',
            'office_id' => $officeId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $queueId = DB::table('queues')->insertGetId([
            'counter_id' => $counterId,
            'name' => 'Counter 1 Queue',
            'status' => 'NORMAL',
            'members_waiting' => 1,
            'members_being_served' => 1,
            'average_wait_time' => 5,
            'office_id' => $officeId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('tickets')->insert([
            'tenant_id' => $tenantId,
            'ticket_number' => 'A100',
            'service_type' => 'Benefits',
            'service_id' => null,
            'queue_id' => $queueId,
            'member_number' => null,
            'member_name' => null,
            'phone_number' => null,
            'estimated_time' => 300,
            'priority' => 0,
            'status' => 'waiting',
            'counter_id' => (string) $counterId,
            'clerk_id' => null,
            'called_at' => null,
            'serving_started_at' => null,
            'completed_at' => null,
            'duration_seconds' => null,
            'transferred_to_counter_id' => null,
            'office_id' => $officeId,
            'queue_position' => 1,
            'created_by' => null,
            'updated_by' => null,
            'deleted_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedClerkAssignment(int $clerkId, int $tenantId, string $officeId): void
    {
        $counterTypeId = DB::table('counter_types')->insertGetId([
            'tenant_id' => $tenantId,
            'name' => 'Clerk Counter Type',
            'code' => 'CLERK_COUNTER',
            'description' => 'Clerk counter type',
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $counterId = DB::table('counters')->insertGetId([
            'tenant_id' => $tenantId,
            'name' => 'Counter 2',
            'counter_type_id' => $counterTypeId,
            'status' => 'ACTIVE',
            'office_id' => $officeId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('counter_clerk')->insert([
            'tenant_id' => $tenantId,
            'counter_id' => $counterId,
            'clerk_id' => (string) $clerkId,
            'is_active' => 1,
            'assigned_at' => now(),
            'unassigned_at' => null,
            'created_by' => null,
            'updated_by' => null,
            'deleted_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
