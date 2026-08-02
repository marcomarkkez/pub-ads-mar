<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\RolePermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PermissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = RolePermission::query();

        if ($request->has('role')) {
            $request->validate(['role' => 'required|in:' . implode(',', RolePermission::ROLES)]);
            $query->where('role', $request->role);
        }

        $permissions = $query->orderBy('role')->orderBy('resource')->orderBy('action')->get();

        $grouped = [];
        foreach ($permissions as $perm) {
            $grouped[$perm->role][$perm->resource][] = $perm->action;
        }

        return response()->json([
            'permissions' => $grouped,
            'available_roles' => RolePermission::ROLES,
            'available_resources' => RolePermission::RESOURCES,
            'available_actions' => RolePermission::ACTIONS,
        ]);
    }

    public function show(string $role): JsonResponse
    {
        if (!in_array($role, RolePermission::ROLES)) {
            return response()->json(['message' => 'Invalid role.'], 404);
        }

        $permissions = RolePermission::where('role', $role)
            ->orderBy('resource')
            ->orderBy('action')
            ->get();

        $matrix = [];
        foreach (RolePermission::RESOURCES as $resource) {
            $matrix[$resource] = [];
            foreach (RolePermission::ACTIONS as $action) {
                $matrix[$resource][$action] = false;
            }
        }
        foreach ($permissions as $perm) {
            if ($perm->allowed) {
                $matrix[$perm->resource][$perm->action] = true;
            }
        }

        return response()->json([
            'role' => $role,
            'permissions' => $matrix,
        ]);
    }

    /**
     * The role's granted actions as `resource => [action, ...]` — the shape the
     * audit entry mirrors, narrowed to one resource when $resource is given.
     *
     * @return array<string, list<string>>
     */
    private function matrixFor(string $role, ?string $resource = null): array
    {
        $query = RolePermission::where('role', $role)->where('allowed', true);

        if ($resource !== null) {
            $query->where('resource', $resource);
        }

        $matrix = [];
        foreach ($query->orderBy('resource')->orderBy('action')->get() as $perm) {
            $matrix[$perm->resource][] = $perm->action;
        }

        return $matrix;
    }

    public function update(Request $request, string $role): JsonResponse
    {
        if (!in_array($role, RolePermission::ROLES)) {
            return response()->json(['message' => 'Invalid role.'], 404);
        }

        $validated = $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'array',
            'permissions.*.*' => 'in:' . implode(',', RolePermission::ACTIONS),
        ]);

        foreach (array_keys($validated['permissions']) as $resource) {
            if (!in_array($resource, RolePermission::RESOURCES)) {
                return response()->json([
                    'message' => "Invalid resource: {$resource}",
                    'valid_resources' => RolePermission::RESOURCES,
                ], 422);
            }
        }

        // Rewriting who may do what is the most consequential ✏ in §2, so it is
        // 📝-logged whole: the delete+insert leaves no single row to point at, so
        // the entry targets the role's permission SET (target_id null).
        $before = $this->matrixFor($role);

        DB::transaction(function () use ($request, $role, $validated, $before) {
            RolePermission::where('role', $role)->delete();

            $rows = [];
            $now = now();
            foreach ($validated['permissions'] as $resource => $actions) {
                foreach ($actions as $action) {
                    $rows[] = [
                        'role' => $role,
                        'resource' => $resource,
                        'action' => $action,
                        'allowed' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            if (!empty($rows)) {
                RolePermission::insert($rows);
            }

            RolePermission::clearCache($role);

            AuditLog::recordOn(
                $request->user(),
                'update',
                'role_permissions',
                null,
                $before,
                $this->matrixFor($role),
                'admin rewrote the RBAC matrix of role ' . $role . ' (§2 ✏)',
            );
        });

        return response()->json([
            'message' => "Permissions updated for role: {$role}.",
            'role' => $role,
            'permissions' => $validated['permissions'],
        ]);
    }

    public function updateResource(Request $request, string $role, string $resource): JsonResponse
    {
        if (!in_array($role, RolePermission::ROLES)) {
            return response()->json(['message' => 'Invalid role.'], 404);
        }
        if (!in_array($resource, RolePermission::RESOURCES)) {
            return response()->json(['message' => 'Invalid resource.'], 404);
        }

        $validated = $request->validate([
            'actions' => 'required|array',
            'actions.*' => 'in:' . implode(',', RolePermission::ACTIONS),
        ]);

        $before = $this->matrixFor($role, $resource);

        DB::transaction(function () use ($request, $role, $resource, $validated, $before) {
            RolePermission::where('role', $role)->where('resource', $resource)->delete();

            $rows = [];
            $now = now();
            foreach ($validated['actions'] as $action) {
                $rows[] = [
                    'role' => $role,
                    'resource' => $resource,
                    'action' => $action,
                    'allowed' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (!empty($rows)) {
                RolePermission::insert($rows);
            }

            RolePermission::clearCache($role);

            AuditLog::recordOn(
                $request->user(),
                'update',
                'role_permissions',
                null,
                $before,
                $this->matrixFor($role, $resource),
                'admin rewrote ' . $role . '.' . $resource . ' in the RBAC matrix (§2 ✏)',
            );
        });

        return response()->json([
            'message' => "Permissions for {$role}.{$resource} updated.",
            'role' => $role,
            'resource' => $resource,
            'actions' => $validated['actions'],
        ]);
    }
}
