<?php

namespace App\Http\Middleware\Concerns;

use App\Enums\ApiErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * EH-14 (`la-fuga-esta-en-el-par-no-en-la-respuesta`) · BR-3 · §21 rule 2.
 *
 * The controller-level sweep converted eighteen refusals to 404 and left the role and
 * permission middleware alone, on the reasoning that a role check names no row — it
 * says "clients do not enter here", which is true of every id and therefore leaks
 * nothing. Read on its own, that reasoning is correct. It is still how the leak got in.
 *
 * `SubstituteBindings` runs BEFORE these middleware, so by the time a role check speaks,
 * a phantom id has already died in the router with 404 and a REAL id has been resolved
 * into a model. The pair is the oracle. Measured on a running server:
 *
 *   GET /api/provider/spaces/999999  as client → 404   (binding failed)
 *   GET /api/provider/spaces/1       as client → 403   (binding succeeded)
 *
 * Subtract the two and a client can enumerate every provider's listing ids without ever
 * reading one. Neither response is wrong by itself, which is why reading them one at a
 * time — the sweep, the audit, this trait's own author — kept approving them. The log at
 * storage/logs/walk.log found it in its first run by putting the two lines next to each
 * other (BR-17).
 *
 * The rule this settles: a refusal on a route WITH parameters must be indistinguishable
 * from a missing row, because the parameters are what makes it about a specific row. On
 * a collection route there is no row to reveal, so 403 stays — and stays useful, since
 * it tells an honest caller what is actually wrong.
 */
trait RefusesWithoutLeaking
{
    protected function refuse(Request $request, array $forbiddenPayload): JsonResponse
    {
        if ($request->route()?->parameters()) {
            // Byte-identical to what bootstrap/app.php renders for NotFoundHttpException,
            // and that matters more than it looks. The first version of this returned
            // `{"message":"Not found."}` while a phantom id got
            // `{"error_code":"NOT_FOUND","message":"Resource not found."}`. Same status,
            // different body — so the oracle survived the fix, just quieter. Matching the
            // status is not the requirement; being INDISTINGUISHABLE is.
            return response()->json([
                'error_code' => ApiErrorCode::NotFound->value,
                'message' => 'Resource not found.',
            ], 404);
        }

        return response()->json($forbiddenPayload, 403);
    }
}
