<?php

namespace Database\Seeders;

use App\Domains\Authentication\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $cqms = DB::table('modules')->where('code', 'CQMS')->first();
        $cms  = DB::table('modules')->where('code', 'CMS')->first();

        if (!$cqms) {
            $this->command->warn('CQMS module not found. Please run ModuleSeeder first.');
            return;
        }

        if (!$cms) {
            $this->command->warn('CMS module not found. Please run ModuleSeeder first.');
            return;
        }

        // Queue Management roles
        $queueRoles = [
            [
                'module_id' => $cqms->id,
                'role_code' => 'QC',
                'role_name' => 'Queue Clerk',
                'created_by' => 1,
            ],
            [
                'module_id' => $cqms->id,
                'role_code' => 'QS',
                'role_name' => 'Queue Supervisor',
                'created_by' => 1,
            ],
            [
                'module_id' => $cqms->id,
                'role_code' => 'QA',
                'role_name' => 'Queue Administrator',
                'created_by' => 1,
            ],
        ];

        // Content Management roles
        $contentRoles = [
            [
                'module_id' => $cms->id,
                'role_code' => 'CMPR',
                'role_name' => 'Content Manager (CMPR)',
                'created_by' => 1,
            ],
        ];

        foreach (array_merge($queueRoles, $contentRoles) as $roleData) {
            Role::firstOrCreate(
                ['role_code' => $roleData['role_code'], 'module_id' => $roleData['module_id']],
                $roleData
            );
        }

        $this->command->info('Roles seeded successfully.');
    }
}
