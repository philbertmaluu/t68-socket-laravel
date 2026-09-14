<?php

namespace Tests\Feature;

use App\Domains\Device\Models\Device;
use App\Domains\Device\Models\DeviceToken;
use App\Domains\SelfService\Support\OtpCodeGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class SelfServiceOtpFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.ictms.endpoint', 'https://ictmspre-api.nssf.go.tz/api/send-notification');
        config()->set('services.ictms.enabled', true);
        config()->set('self_service.demo_enabled', false);

        Http::fake([
            'https://ictmspre-api.nssf.go.tz/api/send-notification' => Http::response(['success' => true], 200),
        ]);

        $this->app->instance(OtpCodeGenerator::class, new class extends OtpCodeGenerator {
            public function generate(int $length = 6): string
            {
                return '123456';
            }
        });
    }

    public function test_kiosk_can_verify_member_send_and_confirm_otp(): void
    {
        $headers = $this->kioskHeaders();
        $this->seedMember('NSSF001', 'Jane Member', '0718206671');

        $verify = $this->postJson('/api/qms/self-services/members/verify', [
            'member_number' => 'nssf001',
        ], $headers);

        $verify->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.member_number', 'NSSF001')
            ->assertJsonPath('data.member_name', 'Jane Member')
            ->assertJsonPath('data.masked_phone', '07******71');

        $challengeId = $verify->json('data.challenge_id');
        $this->assertNotEmpty($challengeId);

        $this->postJson('/api/qms/self-services/otp/send', [
            'member_number' => 'NSSF001',
            'challenge_id' => $challengeId,
            'locale' => 'en',
        ], $headers)
            ->assertOk()
            ->assertJsonPath('success', true);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return ($data['notification_type'] ?? null) === 'sms'
                && ($data['notification_process'] ?? null) === 'SELF SERVICE OTP'
                && ($data['notification_recipient'] ?? null) === '0718206671'
                && str_contains((string) ($data['notification_body'] ?? ''), '123456');
        });

        $this->postJson('/api/qms/self-services/otp/verify', [
            'member_number' => 'NSSF001',
            'challenge_id' => $challengeId,
            'otp' => '123456',
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.verified', true)
            ->assertJsonPath('data.success', true);
    }

    public function test_unknown_member_returns_not_found(): void
    {
        $this->postJson('/api/qms/self-services/members/verify', [
            'member_number' => 'MISSING1',
        ], $this->kioskHeaders())
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Member not found');
    }

    public function test_invalid_otp_is_rejected(): void
    {
        $headers = $this->kioskHeaders();
        $this->seedMember('NSSF002', 'John Member', '0748304649');

        $challengeId = $this->postJson('/api/qms/self-services/members/verify', [
            'member_number' => 'NSSF002',
        ], $headers)->json('data.challenge_id');

        $this->postJson('/api/qms/self-services/otp/send', [
            'member_number' => 'NSSF002',
            'challenge_id' => $challengeId,
        ], $headers)->assertOk();

        $this->postJson('/api/qms/self-services/otp/verify', [
            'member_number' => 'NSSF002',
            'challenge_id' => $challengeId,
            'otp' => '000000',
        ], $headers)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Invalid OTP');
    }

    public function test_device_token_is_required(): void
    {
        $this->postJson('/api/qms/self-services/members/verify', [
            'member_number' => 'NSSF001',
        ])->assertStatus(401);
    }

    /**
     * @return array<string, string>
     */
    private function kioskHeaders(): array
    {
        $this->seedTenant();

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

    private function seedMember(string $memberNumber, string $name, string $phone): void
    {
        $this->seedTenant();

        DB::table('self_service_members')->insert([
            'tenant_id' => 1,
            'member_number' => $memberNumber,
            'member_name' => $name,
            'phone' => $phone,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
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
}
