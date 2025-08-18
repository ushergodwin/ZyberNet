<?php

namespace Database\Seeders;

use App\Models\RouterConfiguration;
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
        // 1. Create Roles
        $adminRole = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $managerRole = Role::firstOrCreate(['name' => 'WiFi Manager', 'guard_name' => 'web']);

        $routers = RouterConfiguration::all();
        $router_permissions = [];

        foreach ($routers as $router) {
            // Create access permissions for each router
            $router_permissions[] = 'manage_' . str_replace(' ', '_', strtolower($router->name)) . ':' . $router->id;
        }

        $router_permissions = array_merge($router_permissions, [
            'view_router',
            'create_router',
            'edit_router',
            'delete_router',
            'test_connection',
        ]);

        // 2. Define permissions in groups for easy assignment
        $permissions = [
            'router' => $router_permissions,
            'router_logs' => [
                'view_router_logs',
            ],
            'vouchers' => [
                'view_vouchers',
                'create_vouchers',
                'edit_vouchers',
                'delete_vouchers',
                'print_vouchers',
            ],
            'payments' => [
                'view_payments',
                'check_payment_status',
            ],
            'users' => [
                'view_users',
                'create_users',
                'edit_users',
                'delete_users',
                'update_user'
            ],
            'data_plans' => [
                'view_data_plans',
                'create_data_plans',
                'edit_data_plans',
                'delete_data_plans',
            ],
            'dashboard' => [
                'view_revenue_stats',
                'view_router_stats',
            ],
            'settings' => [
                'view_settings',
                'edit_settings',
            ],
            'support_contacts' => [
                'view_support_contacts',
                'create_support_contacts',
                'edit_support_contacts',
                'delete_support_contacts',
            ],
            'permissions' => [
                'view_permissions',
                'assign_permissions',
            ],
            'roles' => [
                'view_roles',
                'create_roles',
                'edit_roles',
                'delete_roles',
            ],
        ];

        // 3. Create permissions and collect them for assignment
        $allPermissions = [];
        foreach ($permissions as $group => $perms) {
            foreach ($perms as $perm) {
                $p = Permission::firstOrCreate(
                    ['name' => $perm, 'guard_name' => 'web']
                );
                $allPermissions[] = $p;
            }
        }


        // 4. Assign permissions to roles
        // Admin gets everything
        $adminRole->syncPermissions($allPermissions);

        // Manager gets limited permissions
        $managerPermissions = [
            'view_router',
            'view_router_logs',
            'view_vouchers',
            'print_vouchers',
            'view_payments',
            'check_payment_status',
            'view_users',
            'view_data_plans',
            'view_revenue_stats',
            'view_router_stats',
        ];

        $managerRole->syncPermissions($managerPermissions);
    }
}
