<?php

namespace Tests\Feature;

use App\Domains\Authentication\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminDashboardFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_requires_authentication(): void
    {
        $this->getJson('/api/qms/dashboard/admin')
            ->assertUnauthorized();
    }

    public function test_admin_dashboard_returns_regions_payload(): void
    {
        Sanctum::actingAs($this->createClerk());

        $this->getJson('/api/qms/dashboard/admin')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'regions',
                    'meta' => ['dashboard', 'generatedAt', 'source'],
                ],
            ])
            ->assertJsonPath('data.meta.dashboard', 'admin');
    }

    private function createClerk(): User
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

        return User::create([
            'tenant_id' => 1,
            'user_id' => 'PF'.fake()->numerify('#####'),
            'user_type' => 'staff',
            'name' => fake()->unique()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
    }
}
