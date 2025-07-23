<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class PermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'manager']);

        Permission::firstOrCreate(['name' => 'view_router']);
        Permission::firstOrCreate(['name' => 'firstOrCreate_router']);
        Permission::firstOrCreate(['name' => 'edit_router']);
        Permission::firstOrCreate(['name' => 'delete_router']);
        // router logs
        Permission::firstOrCreate(['name' => 'view_router_logs']);
        Permission::firstOrCreate(['name' => 'view_vouchers']);
        Permission::firstOrCreate(['name' => 'firstOrCreate_vouchers']);
        Permission::firstOrCreate(['name' => 'edit_vouchers']);
        Permission::firstOrCreate(['name' => 'delete_vouchers']);
        // print vouchers
        Permission::firstOrCreate(['name' => 'print_vouchers']);
        // payments
        Permission::firstOrCreate(['name' => 'view_payments']);
        // check payment status
        Permission::firstOrCreate(['name' => 'check_payment_status']);
        // users
        Permission::firstOrCreate(['name' => 'view_users']);
        Permission::firstOrCreate(['name' => 'firstOrCreate_users']);
        Permission::firstOrCreate(['name' => 'edit_users']);
        Permission::firstOrCreate(['name' => 'delete_users']);

        // data plans
        Permission::firstOrCreate(['name' => 'view_data_plans']);
        Permission::firstOrCreate(['name' => 'firstOrCreate_data_plans']);
        Permission::firstOrCreate(['name' => 'edit_data_plans']);

        // dashboard
        Permission::firstOrCreate(['name' => 'view_dashboard']);
    }
}