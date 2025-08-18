<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class RolePermissionController extends Controller
{
    // Get all roles with permissions
    public function index()
    {
        $roles = Role::with('permissions')->get();
        return response()->json($roles);
    }

    // Get all permissions
    public function permissions()
    {
        $permissions = Permission::all();
        return response()->json($permissions);
    }

    // Assign permissions to a role
    public function assignPermissions(Request $request, $roleId)
    {
        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        // Remove existing permissions
        DB::table('role_has_permissions')->where('role_id', $roleId)->delete();

        // Add new ones
        $permissionIds = DB::table('permissions')
            ->whereIn('name', $request->permissions)
            ->pluck('id');

        $data = [];
        foreach ($permissionIds as $pid) {
            $data[] = [
                'permission_id' => $pid,
                'role_id'       => $roleId,
            ];
        }

        DB::table('role_has_permissions')->insert($data);

        // Return updated role
        $role = DB::table('roles')->where('id', $roleId)->first();
        $role->permissions = DB::table('permissions')
            ->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('role_has_permissions.role_id', $roleId)
            ->get();

        return response()->json([
            'message' => 'Permissions updated successfully.',
            'role' => $role,
        ]);
    }

    public function store(Request $request)
    {
        // Trim and lowercase for comparison
        $roleName = trim($request->name);

        // Validate
        $request->validate([
            'name' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    if (DB::table('roles')
                        ->whereRaw('LOWER(name) = ?', [strtolower($value)])
                        ->exists()
                    ) {
                        $fail('Role already exists.');
                    }
                },
            ],
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        // Insert role manually into DB
        $roleId = DB::table('roles')->insertGetId([
            'name' => $roleName,
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Assign permissions if any
        if (!empty($request->permissions)) {
            $permissionIds = DB::table('permissions')
                ->whereIn('name', $request->permissions)
                ->pluck('id');

            $data = [];
            foreach ($permissionIds as $pid) {
                $data[] = [
                    'permission_id' => $pid,
                    'role_id'       => $roleId,
                ];
            }
            DB::table('role_has_permissions')->insert($data);
        }

        // Return with permissions loaded
        $role = DB::table('roles')->where('id', $roleId)->first();
        $role->permissions = DB::table('permissions')
            ->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('role_has_permissions.role_id', $roleId)
            ->get();

        return response()->json([
            'message' => 'Role created successfully.',
            'role' => $role,
        ]);
    }

    // Assign roles to a user - FIXED VERSION
    public function assignRolesToUser(Request $request, User $user)
    {
        $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'string',
        ]);

        // Check if all roles exist before attempting to assign them
        $existingRoles = Role::whereIn('name', $request->roles)->pluck('name')->toArray();
        $missingRoles = array_diff($request->roles, $existingRoles);

        if (!empty($missingRoles)) {
            return response()->json([
                'message' => 'The following roles do not exist: ' . implode(', ', $missingRoles),
                'missing_roles' => $missingRoles,
                'existing_roles' => $existingRoles,
            ], 400);
        }

        try {
            $user->syncRoles($request->roles);

            return response()->json([
                'message' => 'Roles assigned successfully.',
                'user' => $user->load('roles'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to assign roles: ' . $e->getMessage(),
            ], 500);
        }
    }

    // Alternative method to create role if it doesn't exist
    public function assignRolesToUserWithAutoCreate(Request $request, User $user)
    {
        $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'string',
        ]);

        // Create roles if they don't exist
        foreach ($request->roles as $roleName) {
            Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web'
            ]);
        }

        $user->syncRoles($request->roles);

        return response()->json([
            'message' => 'Roles assigned successfully.',
            'user' => $user->load('roles'),
        ]);
    }

    public function destroy($id)
    {
        // Check if the role exists
        $roleExists = DB::table('roles')->where('id', $id)->exists();
        if (!$roleExists) {
            return response()->json(['message' => 'Role not found.'], 404);
        }

        // Check if any users have this role
        $usersWithRole = DB::table('model_has_roles')
            ->where('role_id', $id)
            ->where('model_type', User::class)
            ->count();

        if ($usersWithRole > 0) {
            return response()->json([
                'message' => 'Cannot delete role. It is currently assigned to one or more users.'
            ], 400);
        }

        // Delete the role
        DB::table('roles')->where('id', $id)->delete();

        return response()->json([
            'message' => 'Role deleted successfully.',
            'role_id' => $id
        ]);
    }
}
