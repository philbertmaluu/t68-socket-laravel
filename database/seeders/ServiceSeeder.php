<?php

namespace Database\Seeders;

use App\Domains\Service\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ServiceSeeder extends Seeder
{
    /**
     * Seed the global services catalog (office assignment is via office_services).
     *
     * IDs match the NSSF QMS catalog (ID 10 intentionally absent).
     */
    public function run(): void
    {
        $tenant = DB::table('tenants')->first();

        if (!$tenant) {
            $this->command?->warn('No tenant found. Please run TenantSeeder first.');
            return;
        }

        $tenantId = $tenant->id;

        $services = [
            [
                'id' => 1,
                'name' => 'Claim Lodging',
                'description' => 'Kufungua Madai',
                'swahili_name' => 'Kufungua Madai',
                'estimated_time' => 30,
            ],
            [
                'id' => 2,
                'name' => 'Customer Service',
                'description' => 'Huduma Kwa Wateja',
                'swahili_name' => 'Huduma Kwa Wateja',
                'estimated_time' => 20,
            ],
            [
                'id' => 3,
                'name' => 'Response to Queries',
                'description' => 'Majibu ya Hoja Mbali Mbali',
                'swahili_name' => 'Majibu ya Hoja Mbali Mbali',
                'estimated_time' => 25,
            ],
            [
                'id' => 4,
                'name' => 'Under Payment',
                'description' => 'Mapunjo',
                'swahili_name' => 'Mapunjo',
                'estimated_time' => 30,
            ],
            [
                'id' => 5,
                'name' => 'Claim Follow-up',
                'description' => 'Ufuatiliaji',
                'swahili_name' => 'Ufuatiliaji',
                'estimated_time' => 20,
            ],
            [
                'id' => 6,
                'name' => 'Receipting',
                'description' => 'Risiti',
                'swahili_name' => 'Risiti',
                'estimated_time' => 15,
            ],
            [
                'id' => 7,
                'name' => 'SHIB',
                'description' => 'Matibabu',
                'swahili_name' => 'Matibabu',
                'estimated_time' => 30,
            ],
            [
                'id' => 8,
                'name' => 'Problematic Claims',
                'description' => 'Madai yenye Shida',
                'swahili_name' => 'Madai yenye Shida',
                'estimated_time' => 45,
            ],
            [
                'id' => 9,
                'name' => 'Registration',
                'description' => 'Usajili',
                'swahili_name' => 'Usajili',
                'estimated_time' => 25,
            ],
            [
                'id' => 11,
                'name' => 'Claim Identification',
                'description' => 'Utambulisho',
                'swahili_name' => 'Utambulisho',
                'estimated_time' => 20,
            ],
        ];

        foreach ($services as $row) {
            Service::withTrashed()->updateOrCreate(
                ['id' => $row['id']],
                [
                    'tenant_id' => $tenantId,
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'swahili_name' => $row['swahili_name'],
                    'estimated_time' => $row['estimated_time'],
                    'status' => 'ACTIVE',
                    'deleted_at' => null,
                ]
            );
        }

        $this->command?->info('Seeded ' . count($services) . ' catalog services (IDs preserved).');
    }
}
