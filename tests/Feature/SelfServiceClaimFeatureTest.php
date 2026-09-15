<?php

namespace Tests\Feature;

use App\Domains\Device\Models\Device;
use App\Domains\Device\Models\DeviceToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class SelfServiceClaimFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_kiosk_lists_member_claims_from_cfms(): void
    {
        Http::fake([
            'https://cfmspro-api.nssf.go.tz/api/qms/session' => Http::response([
                'success' => true,
                'data' => [
                    'token' => 'member-session-token',
                    'token_type' => 'Bearer',
                    'member_id' => 50057510,
                    'matched' => true,
                ],
            ], 200),
            'https://cfmspro-api.nssf.go.tz/api/qms/claims/50057510' => Http::response([
                'success' => true,
                'data' => [
                    'member_number' => '50057510',
                    'claims' => [
                        [
                            'claim_number' => '88001234',
                            'claim_type' => 'Old Age',
                            'status' => 'processed',
                            'destination' => 'Benefit Processing Supervisor',
                            'current_office' => 'Dar es Salaam',
                            'current_stage_index' => 2,
                            'amount' => 1500000,
                        ],
                    ],
                    'total' => 1,
                ],
            ], 200),
        ]);

        $this->getJson('/api/qms/self-services/members/claims?member_number=50057510', $this->kioskHeaders())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.member_number', '50057510')
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.claims.0.claim_number', '88001234')
            ->assertJsonPath('data.claims.0.destination', 'Benefit Processing Supervisor');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/api/qms/claims/50057510')
                && $request->hasHeader('Authorization', 'Bearer member-session-token');
        });
    }

    public function test_kiosk_loads_claim_detail_from_cfms(): void
    {
        Http::fake([
            'https://cfmspro-api.nssf.go.tz/api/qms/session' => Http::response([
                'success' => true,
                'data' => [
                    'token' => 'member-session-token',
                    'token_type' => 'Bearer',
                    'member_id' => 50057510,
                    'matched' => true,
                ],
            ], 200),
            'https://cfmspro-api.nssf.go.tz/api/qms/claims/50057510/88001234' => Http::response([
                'success' => true,
                'data' => [
                    'claim_number' => '88001234',
                    'member_number' => '50057510',
                    'member_name' => 'Jane Member',
                    'claim_type' => 'Old Age',
                    'amount' => 1500000,
                    'currency' => 'TZS',
                    'status' => 'processed',
                    'outcome' => null,
                    'destination' => 'Benefit Processing Supervisor',
                    'current_office' => 'Dar es Salaam',
                    'current_stage_index' => 2,
                    'stage_completed_at' => [null, null, null, null, null, null, null, null],
                ],
            ], 200),
        ]);

        $this->getJson('/api/qms/self-services/members/claims/88001234?member_number=50057510', $this->kioskHeaders())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.claim_number', '88001234')
            ->assertJsonPath('data.current_stage_index', 2)
            ->assertJsonPath('data.current_office', 'Dar es Salaam');
    }

    public function test_claim_not_found_returns_404(): void
    {
        Http::fake([
            'https://cfmspro-api.nssf.go.tz/api/qms/session' => Http::response([
                'success' => true,
                'data' => ['token' => 'member-session-token'],
            ], 200),
            'https://cfmspro-api.nssf.go.tz/api/qms/claims/50057510/999' => Http::response([
                'success' => false,
                'message' => 'Claim not found.',
            ], 404),
        ]);

        $this->getJson('/api/qms/self-services/members/claims/999?member_number=50057510', $this->kioskHeaders())
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_claims_require_member_number(): void
    {
        $this->getJson('/api/qms/self-services/members/claims', $this->kioskHeaders())
            ->assertStatus(422);
    }

    /**
     * @return array<string, string>
     */
    private function kioskHeaders(): array
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

        $deviceId = DB::table('devices')->insertGetId([
            'tenant_id' => 1,
            'name' => 'Kiosk 1',
            'type' => Device::TYPE_KIOSK,
            'status' => Device::STATUS_ONLINE,
            'region_id' => 'region-1',
            'office_id' => 'office-1',
            'serial_number' => 'KIOSK-'.Str::upper(Str::random(8)),
            'device_key' => Str::upper(Str::random(10)),
            'password' => Crypt::encryptString('secret123'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $token = Str::random(64);
        DeviceToken::query()->create([
            'device_id' => $deviceId,
            'token' => $token,
            'expires_at' => now()->addDay(),
        ]);

        return ['X-Device-Token' => $token];
    }
}
