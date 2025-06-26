<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\VoucherPackage;

class VoucherPackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $packages = [
            [
                'name' => '1 Hour',
                'price' => 1000,
                'profile_name' => '1hour',
                'rate_limit' => '2M/2M',
                'session_timeout' => '1h',
                'limit_bytes_total' => 300000000, // 300MB
                'shared_users' => 1,
                'description' => '1-hour access, 2 Mbps, 300MB cap.',
            ],
            [
                'name' => '2 Hours',
                'price' => 2000,
                'profile_name' => '2hours',
                'rate_limit' => '5M/5M',
                'session_timeout' => '2h',
                'limit_bytes_total' => 800000000, // 800MB
                'shared_users' => 1,
                'description' => '2-hour access, 5 Mbps, 800MB cap.',
            ],
            [
                'name' => '3 Hours',
                'price' => 500,
                'profile_name' => '3hours',
                'rate_limit' => '1M/1M',
                'session_timeout' => '3h',
                'limit_bytes_total' => null, // Unlimited
                'shared_users' => 1,
                'description' => '3 hours, unlimited data. Best for overnight.',
            ],
        ];

        foreach ($packages as $package) {
            VoucherPackage::updateOrCreate(
                ['profile_name' => $package['profile_name']],
                $package
            );
        }
    }
}