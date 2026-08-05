<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * BR-17 · BR-16 — the ground truth a human walkthrough runs against.
 *
 * Every static check in this repo reads the code and guesses. That guessing has now
 * been wrong twice in the same direction: the frontend URL extractor in
 * PlanningCodeCongruenceTest sees `${api}/client/campaigns` but is blind to
 * `this.api + '/admin/moderation'`, to fragments a helper concatenates
 * (`payments/${id}/refund`), and to role→path maps. Each blind spot made the test
 * report a gap that was not there. A reader cannot fix that by reading harder.
 *
 * So this writes down what ACTUALLY happened: one line per API request, with the
 * actor, the route, and the status. During a WALK the person tails it and sees the
 * app's real behaviour instead of somebody's model of it.
 *
 * It earns its keep most on EH-14 (`la-fuga-esta-en-el-par-no-en-la-respuesta`),
 * where the bug lives in neither response but in the DIFFERENCE between two. Reading
 * one refusal can never find it; two adjacent lines in this log show it immediately:
 *
 *   REFUSED 404 GET /api/provider/spaces/999   role=client   params=yes
 *   REFUSED 403 GET /api/provider/spaces/7     role=client   params=yes
 *                ^^^ different answers for "exists" and "does not exist" = an oracle
 *
 * Off unless WALK_TRACE=true. It records paths and roles, never bodies or headers,
 * because a debugging aid that copies PII into a plaintext file has traded one
 * problem for a worse one (§13).
 */
class WalkTrace
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    /**
     * The logging lives in terminate(), not in handle(), and that is not a style choice.
     *
     * A refusal is not returned up the pipeline — it is THROWN. Route model binding
     * raises NotFoundHttpException, the permission middleware aborts, and Laravel turns
     * those into responses in the exception handler, which sits outside this pipeline.
     * Written the obvious way, `$response = $next($request)` never runs for any of them,
     * so the log would faithfully record every success and silently drop every 403, 404
     * and 409 — the exact lines this file exists to produce. It was written the obvious
     * way first, and the empty log said so.
     *
     * terminate() runs after the response is finalised, whichever path produced it.
     */
    public function terminate(Request $request, Response $response): void
    {
        if (! config('walk.trace')) {
            return;
        }

        $user = $request->user();
        $status = $response->getStatusCode();
        $route = $request->route();

        // Whether the route carries parameters is the field that makes the EH-14 pair
        // legible: a refusal on a parameterised route is ABOUT a row, so its status
        // must not depend on whether that row exists. On a collection route there is
        // no row to reveal and a 403 is free.
        $parameterised = $route && $route->parameters() !== [];

        $verdict = match (true) {
            $status >= 500 => 'BROKE  ',
            in_array($status, [401, 403, 404, 409, 422], true) => 'REFUSED',
            default => 'OK     ',
        };

        Log::channel('walk')->info(sprintf(
            '%s %d %s %s   role=%s   user=%s   params=%s',
            $verdict,
            $status,
            $request->method(),
            $request->path(),
            $user?->role ?? 'guest',
            $user?->id ?? '-',
            $parameterised ? 'yes' : 'no',
        ));
    }
}
