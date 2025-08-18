<?php

use App\Models\RouterConfiguration;
use Spatie\Permission\Models\Permission;

if (!function_exists('hasPermission')) {
    /**
     * Check if the currently authenticated user has the given permission.
     *
     * @param string $permission
     * @return bool
     */
    function hasPermission(string $permission): bool
    {
        $user = Auth::user();

        if (!$user) {
            $user = request()->user(); // fallback to request user if Auth facade is not available
        }
        if (!$user) {
            return false; // no logged-in user
        }
        return $user->permissions_list->contains(function ($perm) use ($permission) {
            return $perm === $permission;
        });
    }
}

if (!function_exists('hasAnyPermission')) {
    /**
     * Check if the currently authenticated user has any of the given permissions.
     *
     * @param array $permissions
     * @return bool
     */
    function hasAnyPermission(array $permissions): bool
    {
        $user = Auth::user();

        if (!$user) {
            return false; // no logged-in user
        }

        return $user->hasAnyPermission($permissions); // checks roles + direct permissions
    }
}

if (!function_exists('createRouterPermission')) {
    /**
     * Create a permission for a specific router.
     *
     * @param RouterConfiguration $router
     */
    function createRouterPermission(RouterConfiguration $router)
    {
        $permissionName = 'manage_' . str_replace(' ', '_', strtolower($router->name)) . ':' . $router->id;
        return Permission::firstOrCreate(['name' => $permissionName]);
    }
}

// get router permission name 
if (!function_exists('getRouterPermissionName')) {
    /**
     * Get the permission name for a specific router.
     *
     * @param RouterConfiguration $router
     * @return string
     */
    function getRouterPermissionName(RouterConfiguration $router): string
    {
        return 'manage_' . str_replace(' ', '_', strtolower($router->name)) . ':' . $router->id;
    }
}

// get router id from permission name
if (!function_exists('getRouterIdFromPermissionName')) {
    /**
     * Extract the router ID from a permission name.
     *
     * @param string $permissionName
     * @return int|null
     */
    function getRouterIdFromPermissionName(string $permissionName): ?int
    {
        if (preg_match('/manage_[^:]+:(\d+)/', $permissionName, $matches)) {
            return (int)$matches[1];
        }
        return null; // or throw an exception if needed
    }
}

// get router id assigned to the user depending on their router permissions
if (!function_exists('getUserRouterIds')) {
    /**
     * Get the router ID assigned to the currently authenticated user based on their permissions.
     *
     * @return array
     */
    function getUserRouterIds(): array
    {
        $user = Auth::user();
        if (!$user) {
            return []; // no logged-in user
        }
        $routerIds = [];
        $permissions = $user->getAllPermissions()->filter(function ($permission) {
            return str_starts_with($permission->name, 'manage_');
        });

        foreach ($permissions as $permission) {
            $routerId = getRouterIdFromPermissionName($permission->name);
            if ($routerId) {
                $routerIds[] = $routerId;
            }
        }
        return $routerIds;
    }
}
