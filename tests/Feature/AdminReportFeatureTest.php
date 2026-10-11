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

    public function test_admin_report_export_requires_authentication(): void
    {
        $this->getJson('/api/qms/reports/admin/export?from=2026-01-01&to=2026-01-31&format=excel&view=services')
            ->assertUnauthorized();
    }

    public function test_admin_report_export_rejects_invalid_format(): void
    {
        Sanctum::actingAs($this->createClerk());

        $this->getJson('/api/qms/reports/admin/export?from=2026-01-01&to=2026-01-31&format=xml&view=services')
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_admin_report_export_excel_includes_branding(): void
    {
        Sanctum::actingAs($this->createClerk());

        $response = $this->get('/api/qms/reports/admin/export?from=2026-01-01&to=2026-01-31&format=excel&view=office');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));
        $content = $response->getContent();
        $this->assertNotFalse($content);
        $this->assertStringContainsString('National Social Security Fund', $content);
        $this->assertStringContainsString('Per office', $content);
    }

    public function test_admin_report_export_pdf(): void
    {
        Sanctum::actingAs($this->createClerk());

        $response = $this->get('/api/qms/reports/admin/export?from=2026-01-01&to=2026-01-31&format=pdf&view=services');

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
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
