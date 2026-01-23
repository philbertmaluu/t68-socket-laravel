<?php

namespace Database\Seeders;

use App\Domains\Authentication\Models\Role;
use App\Domains\Authentication\Models\User;
use App\Domains\Authentication\Models\UserRole;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserRoleSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Find the user by user_id (without tenant scope during seeding)
        $user = User::withoutTenant()->where('user_id', '5148')->first();
        
        if (!$user) {
            $this->command->warn('User with user_id 5148 not found. Please run UserSeeder first.');
            return;
        }

        // Get all roles
        $roles = Role::all();
        
        if ($roles->isEmpty()) {
            $this->command->warn('No roles found. Please run RoleSeeder first.');
            return;
        }

        // Assign all roles to the user
        foreach ($roles as $role) {
            UserRole::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'role_id' => $role->id,
                ],
                [
                    'start_date' => now(),
                    'end_date' => null,
                    'status' => 'active',
                    'handover_to_user_id' => null,
                    'handover_date' => null,
                    'created_by' => $user->id,
                ]
            );
        }

        $this->command->info('All roles assigned to user successfully.');
    }
}
