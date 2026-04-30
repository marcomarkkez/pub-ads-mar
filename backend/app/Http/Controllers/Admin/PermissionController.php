<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RolePermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        return response()->json([
            'message' => "Permissions for {$role}.{$resource} updated.",
            'role' => $role,
            'resource' => $resource,
            'actions' => $validated['actions'],
        ]);
    }
}
