<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@purplebug.com'],
            [
                'full_name' => 'Admin',
                'password' => Hash::make('Admin@12345'),
                'role' => 'admin',
                'is_active' => true,
                'failed_login_attempts' => 0,
                'locked_until' => null,
                'email_verified_at' => now(),
            ]
        );
    }
}