<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * §2 (owner 2026-08-03) — `users.delete` stops being a grantable capability.
 *
 * The seeder no longer writes it, but a running database still carries the row from
 * an earlier seed, and RolePermission caches by role. Reseeding is not an option on a
 * live DB (RolePermissionSeeder rewrites the whole matrix), so the grant is revoked
 * surgically here.
 *
 * The route it gated is gone as well; this closes the door behind it so that a future
 * DELETE route cannot quietly inherit a permission that already says yes.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('role_permissions')
            ->where('resource', 'users')
            ->where('action', 'delete')
            ->delete();

        $this->flushPermissionCache();
    }

    /**
     * Restores only what this migration removed: admin was the sole holder (see
     * RolePermissionSeeder before this change — support had users read+update only).
     */
    public function down(): void
    {
        $exists = DB::table('role_permissions')
            ->where('role', 'admin')->where('resource', 'users')->where('action', 'delete')
            ->exists();

        if (! $exists) {
            DB::table('role_permissions')->insert([
                'role' => 'admin',
                'resource' => 'users',
                'action' => 'delete',
                'allowed' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->flushPermissionCache();
    }

    private function flushPermissionCache(): void
    {
        foreach (\App\Models\RolePermission::ROLES as $role) {
            \App\Models\RolePermission::clearCache($role);
        }
    }
};
