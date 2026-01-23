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
        // Get the CQMS module (by code 'CQMS')
        $module = DB::table('modules')->where('code', 'CQMS')->first();
        
        if (!$module) {
            $this->command->warn('CQMS module not found. Please run ModuleSeeder first.');
            return;
        }

        // Use the module's integer ID (primary key)
        $moduleId = $module->id;

        $roles = [
            [
                'module_id' => $moduleId,
                'role_code' => 'QC',
                'role_name' => 'Queue Clerk',
                'created_by' => 1,
            ],
            [
                'module_id' => $moduleId,
                'role_code' => 'QS',
                'role_name' => 'Queue Supervisor',
                'created_by' => 1,
            ],
            [
                'module_id' => $moduleId,
                'role_code' => 'QA',
                'role_name' => 'Queue Administrator',
                'created_by' => 1,
            ],
            [
                'module_id' => $moduleId,
                'role_code' => 'CMPR',
                'role_name' => 'Content Manager (CMPR)',
                'created_by' => 1,
            ],
        ];

        foreach ($roles as $roleData) {
            Role::firstOrCreate(
                ['role_code' => $roleData['role_code'], 'module_id' => $roleData['module_id']],
                $roleData
            );
        }

        $this->command->info('Roles seeded successfully.');
    }
}
