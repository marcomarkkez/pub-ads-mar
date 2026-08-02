<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Chat;
use App\Models\ChatFlag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * UC-22 · design.json §10/§16 — add OR change a flag. On change the current same-type
 * active flag is superseded (active=false, superseded_at set), the new one created,
 * and a kind=system message records it. A flag NEVER grants access; the money STATE
 * stays on payments.status — Support RAISES money flags, Payments EXECUTES via §8.
 */
class ChatFlagController extends Controller
{
    public function store(Request $request, Chat $chat): JsonResponse
    {
        $user = $request->user();

        if (! $chat->userCanAccess($user)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        // Money flags are Support's power (Payments executes); clients/providers do
        // not raise money flags. Support/Payments/Admin may flag.
        if (! $user->isStaff()) {
            return response()->json(['message' => 'Only staff can raise a flag.'], 403);
        }

        $validated = $request->validate([
            'type' => 'required|in:' . implode(',', ChatFlag::TYPES),
            'reason' => 'nullable|string|max:1000',
        ]);

        $flag = DB::transaction(function () use ($chat, $user, $validated) {
            // Supersede the current active flag of the SAME type (change = history).
            $previous = $chat->flags()
                ->where('type', $validated['type'])
                ->where('active', true)
                ->latest('id')
                ->first();

            if ($previous) {
                $previous->update(['active' => false, 'superseded_at' => now()]);
            }

            $flag = $chat->flags()->create([
                'type' => $validated['type'],
                'reason' => $validated['reason'] ?? null,
                'active' => true,
                'created_by_user_id' => $user->id,
            ]);

            $chat->messages()->create([
                'sender_user_id' => $user->id,
                'body' => ($previous ? 'Flag changed → ' : 'Flag raised: ') . $validated['type']
                    . ($validated['reason'] ? ' (' . $validated['reason'] . ')' : ''),
                'kind' => 'system',
            ]);
            $chat->update(['last_message_at' => now()]);

            // §2 · Support's "flag$ 📝": raising or changing a money flag is a staff
            // action on the chat, so it is logged against the chat — the flag row
            // itself is already immutable history (superseded, never edited).
            AuditLog::record(
                $user,
                $previous ? 'flag_change' : 'flag_raise',
                $chat,
                $previous ? ['type' => $previous->type, 'reason' => $previous->reason] : null,
                ['type' => $flag->type, 'reason' => $flag->reason],
                'chat flag ' . $validated['type'] . ' (§2 flag$ · §10 UC-22)',
            );

            return $flag;
        });

        return response()->json(['data' => $flag], 201);
    }
}
