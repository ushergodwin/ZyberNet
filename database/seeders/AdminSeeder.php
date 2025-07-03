<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // seed the default admin user
        User::updateOrCreate([
            'email' => 'admin@superspotwifi.net',
        ], [
            'name' => 'Admin',
            'password' => bcrypt('.NeT#123@M'), // Use a secure password,
            'email_verified_at' => now(),
        ]);
    }
}
