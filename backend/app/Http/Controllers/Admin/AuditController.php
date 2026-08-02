<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * design.json §12 (UC-31) — `GET /admin/audit`.
 *
 * Read-only by construction: this controller exposes no write verb, and the
 * model refuses updates and deletes anyway. §2 gives Admin 👁 read-all on the
 * audit log; Support and Payments write to it but never read it back.
 *
 * Filters are the two the spec names (`target_type`, `target_id`), plus actor
 * and action, which cost nothing and are what an investigation actually starts
 * from ("what did this Support agent touch?").
 */
class AuditController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_type' => 'sometimes|string|max:60',
            'target_id' => 'sometimes|integer',
            'actor_id' => 'sometimes|integer',
            'action' => 'sometimes|string|max:40',
        ]);

        $entries = AuditLog::query()
            ->with('actor:id,name,email,role')
            ->when(
                isset($validated['target_type']),
                fn ($q) => $q->where('target_type', $validated['target_type'])
            )
            ->when(
                isset($validated['target_id']),
                fn ($q) => $q->where('target_id', $validated['target_id'])
            )
            ->when(
                isset($validated['actor_id']),
                fn ($q) => $q->where('actor_id', $validated['actor_id'])
            )
            ->when(
                isset($validated['action']),
                fn ($q) => $q->where('action', $validated['action'])
            )
            ->orderByDesc('id')
            ->paginate(50);

        return response()->json($entries);
    }
}
