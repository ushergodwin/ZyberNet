<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class PaymentTestPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Adds the test_payments permission for payment gateway testing functionality.
     */
    public function run(): void
    {
        // Create the test_payments permission
        $permission = Permission::firstOrCreate([
            'name' => 'test_payments',
            'guard_name' => 'web'
        ]);

        // Assign to Super Admin role
        $adminRole = Role::where('name', 'Super Admin')->first();
        if ($adminRole && !$adminRole->hasPermissionTo('test_payments')) {
            $adminRole->givePermissionTo($permission);
        }

        $this->command->info('Payment test permission created and assigned to Super Admin.');
    }
}
