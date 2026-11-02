<?php

namespace Tests\Feature;

use App\Domains\Authentication\Models\User;
use App\Domains\Authentication\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IctmsCfmsProAccessParityTest extends TestCase
{
    use RefreshDatabase;

    private int $moduleId;

    private int $roleId;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        config()->set('services.ictms.access_enabled', true);

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

        $this->moduleId = (int) DB::table('modules')->insertGetId([
            'module_id' => 'QMS',
            'code' => 'QMS',
            'name' => 'Queue Management',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->roleId = (int) DB::table('roles')->insertGetId([
            'module_id' => $this->moduleId,
            'role_code' => 'CLERK',
            'role_name' => 'Clerk',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_cfmspro_assign_roles_creates_user_and_assigns_role(): void
    {
        $this->postJson('/api/cfmspro/assign-roles', [
            'user_id' => '5549001',
            'role_id' => $this->roleId,
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addYear()->format('Y-m-d'),
            'pfno' => '1000',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status_code', 1)
            ->assertJsonPath('message', 'Role assigned to user successful')
            ->assertJsonPath('data', []);

        $user = User::withoutTenant()->where('user_id', '5549001')->first();
        $this->assertNotNull($user);
        $this->assertSame(1, UserRole::query()
            ->where('user_id', $user->id)
            ->where('role_id', $this->roleId)
            ->where('status', 'active')
            ->count());
    }

    public function test_cfmspro_assign_roles_rejects_duplicate_active_role(): void
    {
        $this->postJson('/api/cfmspro/assign-roles', [
            'user_id' => '5549002',
            'role_id' => $this->roleId,
            'start_date' => now()->format('Y-m-d'),
            'pfno' => '1000',
        ])->assertOk()->assertJsonPath('success', true);

        $this->postJson('/api/cfmspro/assign-roles', [
            'user_id' => '5549002',
            'role_id' => $this->roleId,
            'start_date' => now()->format('Y-m-d'),
            'pfno' => '1000',
        ])
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('status_code', 0)
            ->assertJsonPath('message', 'Role Already Exist in User Profile!');
    }

    public function test_legacy_assign_role_still_accepts_pfno_payload(): void
    {
        $this->postJson('/api/assign-role', [
            'PFNO' => '5549003',
            'ROLE_ID' => $this->roleId,
            'FROM_DATE' => now()->format('Y-m-d'),
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Role assigned successfully');

        $this->assertSame(1, User::withoutTenant()->where('user_id', '5549003')->count());
    }

    public function test_assign_role_accepts_hrms_user_pfno_and_from_to(): void
    {
        $this->postJson('/api/assign-role', [
            'USER_PFNO' => '5549010',
            'ROLE_ID' => $this->roleId,
            'FROM' => now()->format('Y-m-d'),
            'TO' => now()->addYear()->format('Y-m-d'),
            'CREATED_BY' => '1000',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $user = User::withoutTenant()->where('user_id', '5549010')->first();
        $this->assertNotNull($user);
        $this->assertSame(1, UserRole::query()
            ->where('user_id', $user->id)
            ->where('role_id', $this->roleId)
            ->count());
    }

    public function test_assign_role_unwraps_ictms_payload_wrapper(): void
    {
        $this->postJson('/api/assign-role', [
            'payload' => [[
                'USER_ID' => '5549011',
                'ROLE_ID' => $this->roleId,
                'FROM' => now()->format('Y-m-d'),
                'CREATED_BY' => '1000',
            ]],
            'request_id' => '17090129',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(1, User::withoutTenant()->where('user_id', '5549011')->count());
    }

    public function test_assign_role_uses_id_when_role_id_null(): void
    {
        $this->postJson('/api/assign-role', [
            'PFNO' => '5549012',
            'role_id' => null,
            'id' => (string) $this->roleId,
            'FROM_DATE' => now()->format('Y-m-d'),
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $user = User::withoutTenant()->where('user_id', '5549012')->first();
        $this->assertNotNull($user);
        $this->assertSame(1, UserRole::query()
            ->where('user_id', $user->id)
            ->where('role_id', $this->roleId)
            ->count());
    }

    public function test_legacy_assign_role_skips_duplicate_without_error(): void
    {
        $payload = [
            'PFNO' => '5549004',
            'ROLE_ID' => $this->roleId,
            'FROM_DATE' => now()->format('Y-m-d'),
        ];

        $this->postJson('/api/assign-role', $payload)->assertOk()->assertJsonPath('success', true);
        $this->postJson('/api/assign-role', $payload)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Role assigned successfully');
    }

    public function test_access_revoke_hard_deletes_and_returns_details(): void
    {
        $this->postJson('/api/cfmspro/assign-roles', [
            'user_id' => '5549005',
            'role_id' => $this->roleId,
            'start_date' => now()->format('Y-m-d'),
            'pfno' => '1000',
        ])->assertOk();

        $response = $this->postJson('/api/access/revoke', [
            'pfno' => '5549005',
            'role_id' => $this->roleId,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Role removed successfully.');

        $data = $response->json('data');
        $this->assertSame('Clerk', $data['role_name']);
        $this->assertSame('Queue Management', $data['module_name']);
        $this->assertNotEmpty($data['user_name']);

        $user = User::withoutTenant()->where('user_id', '5549005')->first();
        $this->assertSame(0, UserRole::query()
            ->where('user_id', $user->id)
            ->where('role_id', $this->roleId)
            ->count());
    }

    public function test_access_revoke_missing_role_returns_cfms_message(): void
    {
        $this->postJson('/api/access/revoke', [
            'pfno' => '999999',
            'role_id' => $this->roleId,
        ])
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'User role not found.');
    }

    public function test_roles_alias_matches_module_roles(): void
    {
        $viaAlias = $this->postJson('/api/roles', ['moduleId' => $this->moduleId])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json('data');

        $viaLegacy = $this->postJson('/api/module/roles', ['moduleId' => $this->moduleId])
            ->assertOk()
            ->json('data');

        $this->assertSame($viaLegacy, $viaAlias);
        $this->assertSame((string) $this->roleId, $viaAlias[0]['role_id']);
        $this->assertSame('Clerk', $viaAlias[0]['role']);
    }

    public function test_cfmspro_users_profile_and_access(): void
    {
        $this->postJson('/api/cfmspro/users-access', ['pfno' => '5549006'])
            ->assertOk()
            ->assertJsonPath('data', 0);

        $this->postJson('/api/cfmspro/assign-roles', [
            'user_id' => '5549006',
            'role_id' => $this->roleId,
            'start_date' => now()->format('Y-m-d'),
            'pfno' => '1000',
        ])->assertOk();

        $this->postJson('/api/cfmspro/users-access', ['pfno' => '5549006'])
            ->assertOk()
            ->assertJsonPath('data', 1);

        $profile = $this->postJson('/api/cfmspro/users-profile', ['pfno' => '5549006'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json('data');

        $this->assertCount(1, $profile);
        $this->assertSame((string) $this->roleId, $profile[0]['role_id']);
        $this->assertSame('Clerk', $profile[0]['role']);
        $this->assertArrayHasKey('start_date', $profile[0]);
        $this->assertArrayHasKey('end_date', $profile[0]);
    }
}
