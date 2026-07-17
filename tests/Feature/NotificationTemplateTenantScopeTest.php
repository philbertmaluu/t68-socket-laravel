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
            collect($response->json('data'))->contains(fn ($row) => (int) ($row['tenant_id'] ?? 0) === 1),
            'Expected templates to belong to default tenant_id=1'
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

    public function test_sms_lookup_finds_global_template_using_ticket_tenant(): void
    {
        $this->seedTenant();

        // Simulate a logged-in admin from another context that would confuse Auth-based scope
        $user = $this->createUser(tenantId: 1);
        Sanctum::actingAs($user);

        $repo = new \App\Domains\Notification\Repositories\NotificationTemplateRepository();
        $template = $repo->findActiveByKeyAndLocale('ticket_created_sms', 'en', 'sms', 1);

        $this->assertNotNull($template);
        $this->assertSame('ticket_created_sms', $template->key);
        $this->assertTrue($template->active);
    }

    public function test_editing_default_tenant_template_keeps_tenant_id_one(): void
    {
        $this->seedTenant();
        $user = $this->createUser(tenantId: 1);
        Sanctum::actingAs($user);

        $template = \App\Domains\Notification\Models\NotificationTemplate::withoutGlobalScope('tenant')
            ->where('tenant_id', 1)
            ->where('key', 'ticket_completed_sms')
            ->where('locale', 'en')
            ->firstOrFail();

        $response = $this->putJson("/api/qms/notification-templates/{$template->id}", [
            'body' => 'Updated completed SMS body {ticketNumber}',
            'active' => true,
        ]);

        $response->assertOk();
        $this->assertSame(1, (int) $response->json('data.tenant_id'));
        $this->assertDatabaseHas('notification_templates', [
            'id' => $template->id,
            'tenant_id' => 1,
            'body' => 'Updated completed SMS body {ticketNumber}',
            'active' => 1,
        ]);
    }

    private function seedTenant(): void
    {
        if (DB::table('tenants')->where('id', 1)->exists()) {
            return;
        }

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
