<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class RolePermission extends Model
{
    protected $fillable = [
        'role',
        'resource',
        'action',
        'allowed',
    ];

    protected function casts(): array
    {
        return [
            'allowed' => 'boolean',
        ];
    }

    public const ROLES = ['client', 'provider', 'admin', 'support', 'payments'];

    public const RESOURCES = [
        'users',
        'campaigns',
        'adsets',
        'ads',
        'spaces',
        'space_photos',
        'space_availabilities',
        'bookings',
        'payments',
        'proofs',
        // design.json §10 — ONE `chats` resource; `tickets` + `conversations` retired.
        'chats',
        'invoices',
        'collaborators',
        'configurations',
        'dashboard',
        // design.json §12 (UC-31) — read-only for Admin; the log is append-only.
        'audit',
    ];

    public const ACTIONS = ['create', 'read', 'update', 'delete', 'refund'];

    public static function roleHasPermission(string $role, string $resource, string $action): bool
    {
        $permissions = self::getCachedPermissions($role);

        return isset($permissions["{$resource}.{$action}"]);
    }

    public static function getCachedPermissions(string $role): array
    {
        return Cache::remember(
            "role_permissions:{$role}",
            now()->addMinutes(60),
            function () use ($role) {
                return self::where('role', $role)
                    ->where('allowed', true)
                    ->get()
                    ->mapWithKeys(fn ($p) => ["{$p->resource}.{$p->action}" => true])
                    ->toArray();
            }
        );
    }

    public static function clearCache(?string $role = null): void
    {
        if ($role) {
            Cache::forget("role_permissions:{$role}");
        } else {
            foreach (self::ROLES as $r) {
                Cache::forget("role_permissions:{$r}");
            }
        }
    }
}
