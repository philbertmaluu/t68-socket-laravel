<?php

namespace Database\Seeders;

use App\Domains\Service\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the first tenant (or create a default one)
        $tenant = DB::table('tenants')->first();
        
        if (!$tenant) {
            $this->command->warn('No tenant found. Please run TenantSeeder first.');
            return;
        }
        
        $tenantId = $tenant->id;
        
        // Default region and office (adjust as needed)
        // These are just string IDs, not foreign keys, so we can use any value
        $regionId = '1';
        $officeId = '1';

        $services = [
            [
                'id' => 1,
                'name' => 'Claim Lodging',
                'swahili_name' => 'Kufungua Madai',
                'code' => 'C',
                'estimated_time' => 30,
            ],
            [
                'id' => 2,
                'name' => 'Customer Service',
                'swahili_name' => 'Huduma Kwa Wateja',
                'code' => 'E',
                'estimated_time' => 20,
            ],
            [
                'id' => 3,
                'name' => 'Response to Queries',
                'swahili_name' => 'Majibu ya Hoja Mbali Mbali',
                'code' => 'L',
                'estimated_time' => 25,
            ],
            [
                'id' => 4,
                'name' => 'Under Payment',
                'swahili_name' => 'Mapunjo',
                'code' => 'U',
                'estimated_time' => 30,
            ],
            [
                'id' => 5,
                'name' => 'Claim Follow-up',
                'swahili_name' => 'Ufuatiliaji',
                'code' => 'O',
                'estimated_time' => 20,
            ],
            [
                'id' => 6,
                'name' => 'Receipting',
                'swahili_name' => 'Risiti',
                'code' => 'R',
                'estimated_time' => 15,
            ],
            [
                'id' => 7,
                'name' => 'SHIB',
                'swahili_name' => 'Matibabu',
                'code' => 'T',
                'estimated_time' => 30,
            ],
            [
                'id' => 8,
                'name' => 'Problematic Claims',
                'swahili_name' => 'Madai yenye Shida',
                'code' => 'F',
                'estimated_time' => 45,
            ],
            [
                'id' => 9,
                'name' => 'Registration',
                'swahili_name' => 'Usajili',
                'code' => 'G',
                'estimated_time' => 25,
            ],
            [
                'id' => 11,
                'name' => 'Claim Identification',
                'swahili_name' => 'Utambulisho',
                'code' => 'D',
                'estimated_time' => 20,
            ],
            [
                'id' => 12,
                'name' => 'Supervisor',
                'swahili_name' => 'Msimamizi',
                'code' => 'P',
                'estimated_time' => 30,
            ],
            [
                'id' => 13,
                'name' => 'Informal Sector',
                'swahili_name' => 'Sekta isiyo rasmi',
                'code' => 'I',
                'estimated_time' => 25,
            ],
            [
                'id' => 14,
                'name' => 'Special Needs',
                'swahili_name' => 'Mahitaji Maalum',
                'code' => 'N',
                'estimated_time' => 30,
            ],
            [
                'id' => 15,
                'name' => 'Claim Lodging Pensioner',
                'swahili_name' => 'Kufungua Madai Wazee',
                'code' => 'W',
                'estimated_time' => 35,
            ],
            [
                'id' => 16,
                'name' => 'Complaints',
                'swahili_name' => 'Malalamiko',
                'code' => 'M',
                'estimated_time' => 30,
            ],
            [
                'id' => 17,
                'name' => 'Voucher',
                'swahili_name' => 'Vocha',
                'code' => null,
                'estimated_time' => 15,
            ],
        ];

        foreach ($services as $service) {
            Service::updateOrCreate(
                ['id' => $service['id']],
                [
                    'tenant_id' => $tenantId,
                    'name' => $service['name'],
                    'swahili_name' => $service['swahili_name'],
                    'description' => $service['swahili_name'], // Use Swahili name as description
                    'estimated_time' => $service['estimated_time'],
                    'status' => 'ACTIVE',
                    'region_id' => $regionId,
                    'office_id' => $officeId,
                ]
            );
        }
    }
}
