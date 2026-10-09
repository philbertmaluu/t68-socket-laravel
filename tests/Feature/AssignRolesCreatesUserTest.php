<?php

namespace Tests\Feature;

use App\Domains\Authentication\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AssignRolesCreatesUserTest extends TestCase
{
    use RefreshDatabase;

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

        $moduleId = DB::table('modules')->insertGetId([
            'module_id' => 'QMS',
            'code' => 'QMS',
            'name' => 'Queue Management',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->roleId = (int) DB::table('roles')->insertGetId([
            'module_id' => $moduleId,
            'role_code' => 'CLERK',
            'role_name' => 'Clerk',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_assign_roles_creates_user_when_pfno_does_not_exist(): void
    {
        $this->assertSame(0, User::withoutTenant()->where('user_id', '998877')->count());

        $this->postJson('/api/assign-roles', [
            'PFNO' => '998877',
            'ROLE_ID' => $this->roleId,
            'FROM_DATE' => now()->format('Y-m-d'),
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.pfno', '998877')
            ->assertJsonPath('data.0.created', true);

        $this->assertSame(1, User::withoutTenant()->where('user_id', '998877')->count());
    }

    public function test_assign_roles_does_not_create_user_when_pfno_exists(): void
    {
        User::withoutTenant()->create([
            'tenant_id' => 1,
            'user_id' => '112233',
            'user_type' => 'staff',
            'name' => 'Existing Staff 112233',
            'email' => 'existing112233@nssf.local',
            'password' => 'secret',
            'is_active' => true,
        ]);

        $this->postJson('/api/assign-roles', [
            [
                'PFNO' => 112233,
                'ROLE_ID' => $this->roleId,
                'FROM_DATE' => now()->format('Y-m-d'),
                'TO_DATE' => now()->addYear()->format('Y-m-d'),
                'CREATED_BY' => 112233,
            ],
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.pfno', '112233')
            ->assertJsonPath('data.0.created', false);

        $this->assertSame(1, User::withoutTenant()->where('user_id', '112233')->count());
    }
}
