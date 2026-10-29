<?php

namespace App\Console\Commands;

use App\Models\User;
use Common\Auth\Permissions\Permission;
use Common\Auth\Roles\Role;
use Illuminate\Console\Command;

class EnsureChatPermissions extends Command
{
    protected $signature = 'chat:ensure-permissions {--user-id= : Specific user ID to check}';
    protected $description = 'Ensure all users have chat permissions';

    public function handle()
    {
        $chatViewPermission = Permission::where('name', 'chat.view')->first();
        $chatSendPermission = Permission::where('name', 'chat.send')->first();
        $adminPermission = Permission::where('name', 'admin')->first();

        if (!$chatViewPermission || !$chatSendPermission) {
            $this->error('Chat permissions not found. Please run PermissionTableSeeder first.');
            return 1;
        }

        $defaultRole = Role::where('default', true)->where('type', 'users')->first();

        if (!$defaultRole) {
            $this->error('Default Users role not found.');
            return 1;
        }

        $defaultRole->permissions()->syncWithoutDetaching([
            $chatViewPermission->id,
            $chatSendPermission->id,
        ]);

        $this->info('Chat permissions added to default Users role.');

        $userId = $this->option('user-id');
        if ($userId) {
            $user = User::find($userId);
            if (!$user) {
                $this->error("User with ID {$userId} not found.");
                return 1;
            }

            $this->info("\nChecking user: {$user->email} (ID: {$user->id})");
            
            $hasAdmin = $user->hasPermission('admin');
            $hasChatView = $user->hasPermission('chat.view');
            $hasChatSend = $user->hasPermission('chat.send');

            $this->info("Has admin permission: " . ($hasAdmin ? 'YES' : 'NO'));
            $this->info("Has chat.view permission: " . ($hasChatView ? 'YES' : 'NO'));
            $this->info("Has chat.send permission: " . ($hasChatSend ? 'YES' : 'NO'));

            if (!$hasAdmin && !$hasChatView) {
                $this->warn('User does not have chat.view permission and is not an admin.');
                if ($this->confirm('Grant chat permissions to this user?')) {
                    $user->permissions()->syncWithoutDetaching([
                        $chatViewPermission->id,
                        $chatSendPermission->id,
                    ]);
                    $this->info('Chat permissions granted.');
                }
            } elseif ($hasAdmin) {
                $this->info('User has admin permission, should have access to everything.');
            }
        } else {
            $usersWithoutChatView = User::whereDoesntHave('permissions', function ($query) {
                $query->whereIn('name', ['chat.view', 'admin']);
            })
                ->whereDoesntHave('roles.permissions', function ($query) {
                    $query->whereIn('name', ['chat.view', 'admin']);
                })
                ->count();

            if ($usersWithoutChatView > 0) {
                $this->info("Found {$usersWithoutChatView} users without chat.view or admin permission.");
                $this->info('These users will get permissions from their default role.');
            }

            $adminUsers = User::whereHas('permissions', function ($query) {
                $query->where('name', 'admin');
            })->get();

            $this->info("\nFound " . $adminUsers->count() . " admin user(s):");
            foreach ($adminUsers as $admin) {
                $this->info("  - {$admin->email} (ID: {$admin->id})");
            }
        }

        $this->info('\nDone! Users should now have chat permissions.');
        $this->info('Note: Admin users should automatically have access via the "admin" permission.');
        return 0;
    }
}
