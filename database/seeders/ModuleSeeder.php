<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ModuleSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $modules = [
            // Add your module data here
            // Example structure:
            [
                'module_id' => '1',
                'code' => 'CQMS',
                'name' => 'Core ',
                'description' => 'Core Queue Management System ',
                'is_active' => true,
                'created_by' => 1,
            ],    
        ];

        foreach ($modules as $moduleData) {
            DB::table('modules')->updateOrInsert(
                ['code' => $moduleData['code']],
                array_merge($moduleData, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $this->command->info('Modules seeded successfully.');
    }
}
