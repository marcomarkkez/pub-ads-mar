<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\RefusesWithoutLeaking;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    use RefusesWithoutLeaking;

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!$request->user() || !in_array($request->user()->role, $roles)) {
            return $this->refuse($request, ['message' => 'Forbidden.']);
        }

        return $next($request);
    }
}
