<?php

use App\Models\RolePermission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * §3 · UC-19 (owner 2026-08-23) — "un proveedor puede tener colaboradores y un cliente
 * también, cada uno es como una empresa".
 *
 * The routes moved out of the `/client` prefix to `/collaborators` under
 * `role:client,provider`, but the move alone changes nothing: they are still gated on
 * `permission:collaborators,{read,create,delete}` and `provider` held none of those cells,
 * so a provider would have reached a route that exists and been refused by the matrix —
 * the same dead end one layer down, and a harder one to read.
 *
 * The same three actions as `client`, because it is the same capability on the same object:
 * list who is in my account, invite someone, revoke them. NOT `update` — nobody has it; a
 * subrole change is Support's audited edit (§11), never the owner's silent one.
 *
 * Idempotent upsert — RolePermissionSeeder truncates and so never runs against a populated
 * database (mvp-init.py skips reseeding one), which is why the grant has to exist in BOTH
 * places: here for the databases that are already up, and in the seeder for the ones that
 * are not yet.
 */
return new class extends Migration
{
    private const GRANTS = [
        ['provider', 'collaborators', 'read'],
        ['provider', 'collaborators', 'create'],
        ['provider', 'collaborators', 'delete'],
    ];

    public function up(): void
    {
        $now = now();

        DB::table('role_permissions')->upsert(
            array_map(fn (array $g) => [
                'role' => $g[0],
                'resource' => $g[1],
                'action' => $g[2],
                'allowed' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ], self::GRANTS),
            ['role', 'resource', 'action'],
            ['allowed', 'updated_at'],
        );

        RolePermission::clearCache();
    }

    /**
     * Removes only what this migration added: `client` keeps its own three cells, and an
     * account's existing collaborator ROWS are untouched — revoking the owner's power to
     * manage them is not the same act as ending the grants.
     */
    public function down(): void
    {
        foreach (self::GRANTS as [$role, $resource, $action]) {
            DB::table('role_permissions')
                ->where('role', $role)
                ->where('resource', $resource)
                ->where('action', $action)
                ->delete();
        }

        RolePermission::clearCache();
    }
};
