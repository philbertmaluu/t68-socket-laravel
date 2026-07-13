<?php

namespace Database\Seeders;

use App\Domains\Service\Models\OfficeService;
use App\Domains\Service\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ServiceSeeder extends Seeder
{
    /**
     * Seed global catalog services and assign them to a default office.
     */
    public function run(): void
    {
        $tenant = DB::table('tenants')->first();

        if (!$tenant) {
            $this->command->warn('No tenant found. Please run TenantSeeder first.');
            return;
        }

        $tenantId = $tenant->id;
        $regionId = '1';
        $regionName = 'Default Region';
        $officeId = '1';
        $officeName = 'Default Office';

        $services = [
            [
                'name' => 'Claim Lodging',
                'swahili_name' => 'Kufungua Madai',
                'estimated_time' => 30,
            ],
            [
                'name' => 'Customer Service',
                'swahili_name' => 'Huduma Kwa Wateja',
                'estimated_time' => 20,
            ],
            [
                'name' => 'Response to Queries',
                'swahili_name' => 'Majibu ya Hoja Mbali Mbali',
                'estimated_time' => 25,
            ],
            [
                'name' => 'Under Payment',
                'swahili_name' => 'Mapunjo',
                'estimated_time' => 30,
            ],
            [
                'name' => 'Claim Follow-up',
                'swahili_name' => 'Ufuatiliaji',
                'estimated_time' => 20,
            ],
            [
                'name' => 'Receipting',
                'swahili_name' => 'Risiti',
                'estimated_time' => 15,
            ],
            [
                'name' => 'SHIB',
                'swahili_name' => 'Matibabu',
                'estimated_time' => 30,
            ],
            [
                'name' => 'Problematic Claims',
                'swahili_name' => 'Madai yenye Shida',
                'estimated_time' => 45,
            ],
            [
                'name' => 'Registration',
                'swahili_name' => 'Usajili',
                'estimated_time' => 25,
            ],
            [
                'name' => 'Claim Identification',
                'swahili_name' => 'Utambulisho',
                'estimated_time' => 20,
            ],
            [
                'name' => 'Supervisor',
                'swahili_name' => 'Msimamizi',
                'estimated_time' => 30,
            ],
            [
                'name' => 'Informal Sector',
                'swahili_name' => 'Sekta isiyo rasmi',
                'estimated_time' => 25,
            ],
            [
                'name' => 'Special Needs',
                'swahili_name' => 'Mahitaji Maalum',
                'estimated_time' => 30,
            ],
            [
                'name' => 'Claim Lodging Pensioner',
                'swahili_name' => 'Kufungua Madai Wazee',
                'estimated_time' => 35,
            ],
            [
                'name' => 'Complaints',
                'swahili_name' => 'Malalamiko',
                'estimated_time' => 30,
            ],
            [
                'name' => 'Voucher',
                'swahili_name' => 'Vocha',
                'estimated_time' => 15,
            ],
        ];

        foreach ($services as $serviceData) {
            $service = Service::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'name' => $serviceData['name'],
                ],
                [
                    'swahili_name' => $serviceData['swahili_name'],
                    'description' => $serviceData['swahili_name'],
                    'estimated_time' => $serviceData['estimated_time'],
                    'status' => 'ACTIVE',
                ]
            );

            OfficeService::withTrashed()->updateOrCreate(
                [
                    'office_id' => $officeId,
                    'service_id' => $service->id,
                ],
                [
                    'tenant_id' => $tenantId,
                    'office_name' => $officeName,
                    'region_id' => $regionId,
                    'region_name' => $regionName,
                    'service_name' => $service->name,
                    'deleted_at' => null,
                ]
            );
        }
    }
}
