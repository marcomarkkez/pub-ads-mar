<?php

namespace App\Http\Middleware;

use App\Models\RolePermission;
use App\Http\Middleware\Concerns\RefusesWithoutLeaking;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    use RefusesWithoutLeaking;

    public function handle(Request $request, Closure $next, string $resource, string $action): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (!RolePermission::roleHasPermission($user->role, $resource, $action)) {
            return $this->refuse($request, [
                'message' => 'Forbidden. You do not have permission to perform this action.',
                'required_permission' => "{$resource}.{$action}",
            ]);
        }

        return $next($request);
    }
}
