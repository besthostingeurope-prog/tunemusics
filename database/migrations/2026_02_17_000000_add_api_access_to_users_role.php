<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Find the api.access permission
        $permission = DB::table('permissions')
            ->where('name', 'api.access')
            ->first();

        if (!$permission) {
            return;
        }

        // Find the default Users role
        $role = DB::table('roles')
            ->where('type', 'users')
            ->where('default', true)
            ->first();

        if (!$role) {
            return;
        }

        // Check if the permission is already assigned to the role
        $exists = DB::table('permissionables')
            ->where('permission_id', $permission->id)
            ->where('permissionable_id', $role->id)
            ->where('permissionable_type', 'role')
            ->exists();

        if (!$exists) {
            DB::table('permissionables')->insert([
                'permission_id' => $permission->id,
                'permissionable_id' => $role->id,
                'permissionable_type' => 'role',
            ]);
        }
    }

    public function down(): void
    {
        $permission = DB::table('permissions')
            ->where('name', 'api.access')
            ->first();

        $role = DB::table('roles')
            ->where('type', 'users')
            ->where('default', true)
            ->first();

        if ($permission && $role) {
            DB::table('permissionables')
                ->where('permission_id', $permission->id)
                ->where('permissionable_id', $role->id)
                ->where('permissionable_type', 'role')
                ->delete();
        }
    }
};
