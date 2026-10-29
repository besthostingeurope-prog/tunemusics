<?php

namespace App\Console\Commands;

use App\Models\User;
use Common\Auth\Permissions\Permission;
use Common\Auth\Roles\Role;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckAdminPermissions extends Command
{
    protected $signature = 'chat:check-admin {--user-id=1}';
    protected $description = 'Check admin user permissions in database';

    public function handle()
    {
        $userId = $this->option('user-id');
        $user = User::find($userId);

        if (!$user) {
            $this->error("User with ID {$userId} not found.");
            return 1;
        }

        $this->info("Checking user: {$user->email} (ID: {$user->id})");
        $this->info("User type: {$user->type}");
        $this->newLine();

        // Check permissions in database
        $this->info("=== DIRECT PERMISSIONS (permissionables table) ===");
        $directPerms = DB::table('permissionables')
            ->join('permissions', 'permissionables.permission_id', '=', 'permissions.id')
            ->where('permissionables.permissionable_id', $user->id)
            ->where('permissionables.permissionable_type', User::class)
            ->select('permissions.name', 'permissions.type')
            ->get();

        if ($directPerms->isEmpty()) {
            $this->warn("  No direct permissions found!");
        } else {
            foreach ($directPerms as $perm) {
                $this->info("  ✓ {$perm->name} (type: {$perm->type})");
            }
        }

        $this->newLine();

        // Check roles
        $this->info("=== ROLES ===");
        $roles = $user->roles;
        if ($roles->isEmpty()) {
            $this->warn("  No roles assigned!");
        } else {
            foreach ($roles as $role) {
                $this->info("  - {$role->name} (ID: {$role->id}, default: " . ($role->default ? 'YES' : 'NO') . ")");
            }
        }

        $this->newLine();

        // Check role permissions
        if ($roles->isNotEmpty()) {
            $this->info("=== ROLE PERMISSIONS ===");
            foreach ($roles as $role) {
                $rolePerms = DB::table('permissionables')
                    ->join('permissions', 'permissionables.permission_id', '=', 'permissions.id')
                    ->where('permissionables.permissionable_id', $role->id)
                    ->where('permissionables.permissionable_type', Role::class)
                    ->select('permissions.name', 'permissions.type')
                    ->get();

                $this->info("  Role: {$role->name}");
                if ($rolePerms->isEmpty()) {
                    $this->warn("    No permissions in this role!");
                } else {
                    foreach ($rolePerms as $perm) {
                        $this->info("    ✓ {$perm->name}");
                    }
                }
            }
        }

        $this->newLine();

        // Check chat permissions exist
        $this->info("=== CHAT PERMISSIONS IN DATABASE ===");
        $chatPerms = Permission::whereIn('name', ['chat.view', 'chat.send', 'chat.moderate', 'admin'])
            ->get(['id', 'name', 'type']);

        foreach ($chatPerms as $perm) {
            $this->info("  {$perm->name} (ID: {$perm->id}, type: {$perm->type})");
        }

        $this->newLine();

        // Test permission check
        $this->info("=== PERMISSION CHECKS (via User model) ===");
        $user->loadPermissions();
        $this->info("  hasPermission('admin'): " . ($user->hasPermission('admin') ? 'YES' : 'NO'));
        $this->info("  hasPermission('chat.view'): " . ($user->hasPermission('chat.view') ? 'YES' : 'NO'));
        $this->info("  hasPermission('chat.send'): " . ($user->hasPermission('chat.send') ? 'YES' : 'NO'));
        $this->info("  hasPermission('chat.moderate'): " . ($user->hasPermission('chat.moderate') ? 'YES' : 'NO'));

        $this->newLine();

        // Check if admin permission exists
        $adminPerm = Permission::where('name', 'admin')->first();
        if (!$adminPerm) {
            $this->error("ERROR: 'admin' permission does not exist in database!");
            $this->info("Run: php artisan db:seed --class=Common\\Database\\Seeders\\PermissionTableSeeder");
            return 1;
        }

        // Check if user has admin permission directly
        $hasAdminDirect = DB::table('permissionables')
            ->where('permissionable_id', $user->id)
            ->where('permissionable_type', User::class)
            ->where('permission_id', $adminPerm->id)
            ->exists();

        if (!$hasAdminDirect) {
            $this->warn("WARNING: User does not have 'admin' permission directly assigned!");
            $this->info("Assigning admin permission...");
            $user->permissions()->syncWithoutDetaching([$adminPerm->id]);
            $this->info("✓ Admin permission assigned!");
        }

        return 0;
    }
}
