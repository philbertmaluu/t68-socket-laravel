<?php

namespace Tests\Feature;

use App\Domains\Authentication\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationTemplateTenantScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_sees_global_sms_templates(): void
    {
        $this->seedTenant();
        $user = $this->createUser(tenantId: 1);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/qms/notification-templates?page=1&per_page=100');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $keys = collect($response->json('data'))->pluck('key')->unique()->values()->all();

        $this->assertContains('ticket_created_sms', $keys);
        $this->assertContains('ticket_completed_sms', $keys);
        $this->assertTrue(
            collect($response->json('data'))->contains(fn ($row) => ($row['tenant_id'] ?? null) === null),
            'Expected at least one global (null tenant) template to be returned'
        );
    }

    public function test_authenticated_user_does_not_see_other_tenant_templates(): void
    {
        $this->seedTenant();
        DB::table('tenants')->insert([
            'id' => 2,
            'name' => 'Tenant B',
            'domain' => 'tenant-b.local',
            'database' => 'tenant_b',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('notification_templates')->insert([
            'tenant_id' => 2,
            'key' => 'other_tenant_only_sms',
            'channel' => 'sms',
            'locale' => 'en',
            'subject' => null,
            'body' => 'Other tenant template',
            'description' => 'Should be hidden',
            'active' => true,
            'created_by' => null,
            'updated_by' => null,
            'deleted_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = $this->createUser(tenantId: 1);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/qms/notification-templates?page=1&per_page=100');

        $response->assertOk();
        $keys = collect($response->json('data'))->pluck('key')->all();
        $this->assertNotContains('other_tenant_only_sms', $keys);
    }

    private function seedTenant(): void
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
    }

    private function createUser(int $tenantId): User
    {
        return User::create([
            'tenant_id' => $tenantId,
            'user_id' => 'PF' . fake()->numerify('#####'),
            'user_type' => 'staff',
            'name' => fake()->unique()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
    }
}
