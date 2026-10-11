<?php

namespace Tests\Feature;

use App\Domains\Authentication\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClerkMemberStatementFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('self_service.cfms.api_base', 'https://cfmspro-api.nssf.go.tz/api');
        config()->set('self_service.cfms.sanctum_token', 'cfms-staff-token');
        Http::preventStrayRequests();
    }

    public function test_member_statement_requires_authentication(): void
    {
        $this->getJson('/api/qms/cfms/member-statement/48264073/1')
            ->assertUnauthorized();
    }

    public function test_member_statement_rejects_invalid_scheme(): void
    {
        Sanctum::actingAs($this->createClerk());

        $this->getJson('/api/qms/cfms/member-statement/48264073/99')
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_member_statement_rejects_invalid_view(): void
    {
        Sanctum::actingAs($this->createClerk());

        $this->getJson('/api/qms/cfms/member-statement/48264073/1?view=other')
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_clerk_can_fetch_statement_pdf(): void
    {
        $pdf = base64_encode("%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
        Http::fake([
            'https://cfmspro-api.nssf.go.tz/api/data-management/member-statement/48264073/1' => Http::response([
                'success' => true,
                'data' => $pdf,
                'message' => 'Successfully retrieved member statement.',
                'status_code' => 1,
            ], 200),
        ]);

        Sanctum::actingAs($this->createClerk());

        $this->getJson('/api/qms/cfms/member-statement/48264073/1')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.member_number', '48264073')
            ->assertJsonPath('data.scheme_id', 1)
            ->assertJsonPath('data.view', 'statement')
            ->assertJsonPath('data.pdf_base64', $pdf);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://cfmspro-api.nssf.go.tz/api/data-management/member-statement/48264073/1'
                && $request->hasHeader('Authorization', 'Bearer cfms-staff-token');
        });
    }

    public function test_clerk_can_fetch_detailed_statement_pdf(): void
    {
        $pdf = base64_encode("%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
        Http::fake([
            'https://cfmspro-api.nssf.go.tz/api/data-management/marked-member-statement/48264073/1' => Http::response([
                'success' => true,
                'data' => $pdf,
                'message' => 'Successfully retrieved marked member statement.',
                'status_code' => 1,
            ], 200),
        ]);

        Sanctum::actingAs($this->createClerk());

        $this->getJson('/api/qms/cfms/member-statement/48264073/1?view=detailed')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.view', 'detailed')
            ->assertJsonPath('data.pdf_base64', $pdf);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://cfmspro-api.nssf.go.tz/api/data-management/marked-member-statement/48264073/1'
                && $request->hasHeader('Authorization', 'Bearer cfms-staff-token');
        });
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
            'user_id' => 'PF' . fake()->numerify('#####'),
            'user_type' => 'staff',
            'name' => fake()->unique()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
    }
}
