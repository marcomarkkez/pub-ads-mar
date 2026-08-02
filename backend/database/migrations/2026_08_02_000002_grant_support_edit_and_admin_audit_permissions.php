<?php

use App\Models\RolePermission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * UC-23 + UC-31 — the RBAC rows the new routes are gated on.
 *
 * RolePermissionSeeder covers a FRESH install, but it truncates, so it never
 * runs against a database that already has data (mvp-init.py skips reseeding a
 * populated DB). Without this migration the new endpoints would 403 on every
 * running environment. Same reasoning that made the support/payments dashboards
 * skip the permission middleware entirely — done properly this time.
 *
 * Idempotent: upsert on the (role, resource, action) unique index.
 */
return new class extends Migration
{
    private const GRANTS = [
        // Support — edit-any-NON-money object (design.md §11, UC-23).
        ['support', 'users', 'update'],
        ['support', 'spaces', 'read'],
        ['support', 'spaces', 'update'],
        ['support', 'ads', 'read'],
        ['support', 'ads', 'update'],
        ['support', 'bookings', 'read'],
        ['support', 'bookings', 'update'],
        ['support', 'collaborators', 'read'],
        ['support', 'collaborators', 'update'],

        // Admin — the immutable audit log (design.md §12, UC-31).
        ['admin', 'audit', 'read'],
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
