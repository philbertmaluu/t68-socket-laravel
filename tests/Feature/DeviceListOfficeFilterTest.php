<?php

namespace Tests\Feature;

use App\Domains\Authentication\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeviceListOfficeFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));

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

        if (!DB::table('modules')->where('id', 1)->exists()) {
            DB::table('modules')->insert([
                'id' => 1,
                'module_id' => 'CQMS',
                'code' => 'CQMS',
                'name' => 'Queue Management',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function test_administrator_lists_all_devices_and_can_filter_by_office(): void
    {
        $admin = $this->createUserWithRole('QA');
        $this->insertDevice('HQ-KIOSK', 'OFF-HQ');
        $this->insertDevice('ARU-KIOSK', 'OFF-ARU');

        Sanctum::actingAs($admin);

        $all = $this->getJson('/api/qms/devices?per_page=500')
            ->assertOk()
            ->assertJsonPath('success', true);

        $allIds = collect($all->json('data'))->pluck('office_id')->all();
        $this->assertContains('OFF-HQ', $allIds);
        $this->assertContains('OFF-ARU', $allIds);

        $filtered = $this->getJson('/api/qms/devices?per_page=500&office_id=OFF-ARU')
            ->assertOk()
            ->assertJsonPath('success', true);

        $officeIds = collect($filtered->json('data'))->pluck('office_id')->unique()->values()->all();
        $this->assertSame(['OFF-ARU'], $officeIds);
    }

    private function createUserWithRole(string $roleCode): User
    {
        $user = User::withoutTenant()->create([
            'tenant_id' => 1,
            'user_id' => 'PF'.fake()->numerify('#####'),
            'user_type' => 'staff',
            'name' => fake()->unique()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'is_active' => true,
        ]);

        $roleId = DB::table('roles')->insertGetId([
            'module_id' => 1,
            'role_code' => $roleCode,
            'role_name' => 'Queue Administrator',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('user_roles')->insert([
            'user_id' => $user->id,
            'role_id' => $roleId,
            'start_date' => now()->subDay(),
            'end_date' => now()->addYear(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user->fresh();
    }

    private function insertDevice(string $name, string $officeId): void
    {
        DB::table('devices')->insertGetId([
            'tenant_id' => 1,
            'name' => $name,
            'type' => 'KIOSK',
            'status' => 'offline',
            'region_id' => 'REG-1',
            'office_id' => $officeId,
            'serial_number' => $name.'-SN',
            'device_key' => strtoupper(substr(md5($name), 0, 10)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
