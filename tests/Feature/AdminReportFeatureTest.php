<?php

namespace Tests\Feature;

use App\Domains\Authentication\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminReportFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_report_requires_authentication(): void
    {
        $this->getJson('/api/qms/reports/admin?from=2026-01-01&to=2026-01-31')
            ->assertUnauthorized();
    }

    public function test_admin_report_rejects_invalid_range(): void
    {
        Sanctum::actingAs($this->createClerk());

        $this->getJson('/api/qms/reports/admin?from=2026-02-01&to=2026-01-01')
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_admin_report_returns_country_payload(): void
    {
        Sanctum::actingAs($this->createClerk());

        $this->getJson('/api/qms/reports/admin?from=2026-01-01&to=2026-01-31')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meta.scope', 'country')
            ->assertJsonStructure([
                'data' => [
                    'reportData',
                    'serviceTypes',
                    'clerkPerformance',
                    'officePerformance',
                    'officeServices',
                    'offices',
                    'meta' => ['scope', 'from', 'to'],
                ],
            ]);
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
