<?php

namespace Database\Seeders;

use App\Domains\Tenant\Models\Tenant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = [
            [
                'name' => 'NSSF',
                'domain' => 'https://portal.nssf.go.tz/',
                'database' => 'QMS-DB',
                'is_active' => true,
                'settings' => [
                    'timezone' => 'UTC',
                    'locale' => 'en',
                ],
                'created_by' => 1,
            ],
        ];

        foreach ($tenants as $tenantData) {
            Tenant::firstOrCreate(
                ['domain' => $tenantData['domain']],
                $tenantData
            );
        }

        $this->command->info('Tenants seeded successfully.');
    }
}
