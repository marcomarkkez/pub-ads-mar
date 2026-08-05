<?php

use App\Enums\ApiErrorCode;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // BR-17 — one line per API request into storage/logs/walk.log, for the human
        // walkthroughs. Inert unless WALK_TRACE=true, and appended LAST so it observes
        // the final status, including refusals produced by the middleware above it.
        $middleware->appendToGroup('api', [
            \App\Http\Middleware\WalkTrace::class,
        ]);

        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'permission' => \App\Http\Middleware\PermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Every JSON error response gets a stable top-level "error_code" so the
        // frontend (and whoever's debugging a failed request) can tell apart
        // "never reached the server", "bad input", "not authenticated", and
        // "server blew up" without parsing human-readable message text.
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error_code' => ApiErrorCode::ValidationFailed->value,
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], $e->status);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error_code' => ApiErrorCode::Unauthenticated->value,
                    'message' => 'Unauthenticated.',
                ], 401);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error_code' => ApiErrorCode::NotFound->value,
                    'message' => 'Resource not found.',
                ], 404);
            }
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->expectsJson() && !config('app.debug')) {
                return response()->json([
                    'error_code' => ApiErrorCode::ServerError->value,
                    'message' => 'Something went wrong. Please try again.',
                ], 500);
            }
        });
    })->create();
