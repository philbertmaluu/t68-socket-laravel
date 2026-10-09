<?php

namespace Tests\Feature;

use App\Domains\Device\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeviceCreateAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        app()->instance('tenant.id', 1);

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

    public function test_creating_a_device_persists_numeric_id_and_audit_row(): void
    {
        $device = Device::create([
            'tenant_id' => 1,
            'name' => 'POS-HQ',
            'type' => Device::TYPE_KIOSK,
            'status' => Device::STATUS_ONLINE,
            'region_id' => '1',
            'office_id' => '42',
            'serial_number' => '1234153532324213178',
            'ip_address' => '10.10.56.87',
            'notes' => 'kiosk',
        ]);

        $this->assertNotNull($device->id);
        $this->assertIsInt($device->id);
        $this->assertGreaterThan(0, $device->id);

        $this->assertDatabaseHas('audit_trails', [
            'auditable_type' => Device::class,
            'auditable_id' => $device->id,
            'event' => 'created',
        ]);
    }
}
