<?php

use App\Models\RolePermission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * UC-29 + UC-32 — the RBAC rows the moderation routes are gated on.
 *
 * A NEW resource (`moderation`) rather than widening `spaces`/`users`/`payments`:
 * §12 keeps Admin READ-ONLY on every object, "EXCEPT these audited moderation
 * actions". Granting admin `spaces.update` to make a takedown work would also say
 * "admin edits listings", which is false and would silently authorise any future
 * route gated on it. `moderation` is the one power admin actually has.
 *
 * Two actions, deliberately split:
 *   moderation.update — takedown / restore / freeze / unfreeze / payout stop.
 *   moderation.refund — CLAWBACK only. It is the single action here that takes money
 *                       BACK from a provider who was already paid, so it is separately
 *                       revocable: an operator can hold the moderation power without
 *                       holding the money-reversal power.
 *
 * Idempotent upsert — RolePermissionSeeder truncates and so never runs against a
 * populated database (mvp-init.py skips reseeding one).
 */
return new class extends Migration
{
    private const GRANTS = [
        ['admin', 'moderation', 'read'],
        ['admin', 'moderation', 'update'],
        ['admin', 'moderation', 'refund'],
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
