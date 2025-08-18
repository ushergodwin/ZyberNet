<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure the admin role exists
        $adminRole = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);

        // Create or update the Admin user
        $admin = User::updateOrCreate(
            ['email' => 'admin@superspotwifi.net'],
            [
                'name' => 'Admin',
                'password' => Hash::make('.NeT#123@M'),
                'email_verified_at' => now(),
            ]
        );

        // Create or update the Ursher Godwin user
        $ursher = User::updateOrCreate(
            ['email' => 'urshergodwin@superspotwifi.net'],
            [
                'name' => 'Ursher Godwin',
                'password' => Hash::make('.WiFi#321@M'),
                'email_verified_at' => now(),
            ]
        );

        // Assign the admin role to both users
        $admin->syncRoles([$adminRole]);
        $ursher->syncRoles([$adminRole]);
    }
}
