<?php

namespace Database\Seeders;

use App\Domains\Authentication\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'tenant_id' => 1,
                'user_id' => '5148', 
                'user_type' => 'staff',
                'name' => 'Daud Mabena',
                'email' => 'daud.mabena@nssf.go.tz',
                'password' => Hash::make('12341234q'),
                'is_active' => true,
                'last_login' => null,
                'refresh_token' => null,
                'email_verified_at' => now(),
                'created_by' => null,
            ],
        ];

        foreach ($users as $userData) {
            User::withoutTenant()->firstOrCreate(
                ['user_id' => $userData['user_id']],
                $userData
            );
        }

        $this->command->info('Users seeded successfully.');
    }
}
