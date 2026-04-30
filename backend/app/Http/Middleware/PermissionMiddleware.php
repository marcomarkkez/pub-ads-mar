<?php

namespace App\Http\Middleware;

use App\Models\RolePermission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $resource, string $action): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (!RolePermission::roleHasPermission($user->role, $resource, $action)) {
            return response()->json([
                'message' => 'Forbidden. You do not have permission to perform this action.',
                'required_permission' => "{$resource}.{$action}",
            ], 403);
        }

        return $next($request);
    }
}
