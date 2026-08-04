<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * design.json §10/§17 — `chats.update` stops being a grantable capability.
 *
 * RolePermissionSeeder granted it to `client` and `provider`, and NOT ONE route in
 * routes/api.php is gated on it: the whole chat lifecycle — post message, attach/detach
 * object, flag, resolve, close — is gated on `chats,create`, and `join` is gated on
 * `role:support`. A grant nothing reads is worse than no grant at all: it reads like a
 * policy and enforces nothing, and the next `chats,update` route to be written inherits
 * a permission that already says yes for two roles nobody re-examined.
 *
 * The seeder no longer writes it, but a running database still carries the rows from an
 * earlier seed, and RolePermission caches by role. Reseeding is not an option on a live DB
 * (RolePermissionSeeder truncates and rewrites the whole matrix), so the grant is revoked
 * surgically here — same pattern and same reasoning as
 * 2026_08_03_000002_revoke_users_delete_permission.php.
 */
return new class extends Migration
{
    /** The only roles that ever held it (RolePermissionSeeder before this change). */
    private const HOLDERS = ['client', 'provider'];

    public function up(): void
    {
        DB::table('role_permissions')
            ->where('resource', 'chats')
            ->where('action', 'update')
            ->delete();

        $this->flushPermissionCache();
    }

    /** Restores only what this migration removed. */
    public function down(): void
    {
        foreach (self::HOLDERS as $role) {
            $exists = DB::table('role_permissions')
                ->where('role', $role)->where('resource', 'chats')->where('action', 'update')
                ->exists();

            if (! $exists) {
                DB::table('role_permissions')->insert([
                    'role' => $role,
                    'resource' => 'chats',
                    'action' => 'update',
                    'allowed' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
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
