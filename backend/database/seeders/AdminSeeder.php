<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Super Admin',
                'email' => 'admin@example.com',
                'password' => Hash::make('password'),
                'role' => 'super_admin',
                'display_name' => 'Administrator',
                'is_active' => true,
            ]
        );

        User::updateOrCreate(
            ['email' => 'editor@example.com'],
            [
                'name' => 'Editor User',
                'email' => 'editor@example.com',
                'password' => Hash::make('password'),
                'role' => 'editor',
                'display_name' => 'Editor',
                'is_active' => true,
            ]
        );
    }
}
