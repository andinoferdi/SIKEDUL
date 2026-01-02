<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        // Create admin user
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'username' => 'admin',
            'phone' => '081234567891',
            'password' => Hash::make('password'),
            'timezone' => 'Asia/Jakarta',
            'role' => 'admin',
            'is_disabled' => false,
            'email_verified_at' => now(),
        ]);

        // Create regular user (unverified)
        User::create([
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'username' => 'regularuser',
            'phone' => '081234567892',
            'password' => Hash::make('password'),
            'timezone' => 'Asia/Jakarta',
            'role' => 'user',
            'is_disabled' => false,
            'email_verified_at' => null, // Unverified
        ]);
    }
}
