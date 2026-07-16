<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * design.md §11 [F09]: Support joins a client↔provider chat via
 * POST /support/conversations/{c}/join. Joining is ANNOUNCED (a system message)
 * and sets support_joined_at, which RELAXES PII masking for the thread (§10 [F08]).
 * Admin observes silently through the read-only oversight endpoints instead —
 * Admin never uses this route, so Admin entry is never announced.
 */
class ConversationController extends Controller
{
    public function join(Request $request, Conversation $conversation): JsonResponse
    {
        // Only the direct client↔provider thread is "joined". Internal Support↔Payments
        // and the Support-anchored dispute threads are already staff-run, so joining
        // them is a no-op that would wrongly announce a join.
        if (($conversation->type ?? Conversation::TYPE_DIRECT) !== Conversation::TYPE_DIRECT) {
            return response()->json(['message' => 'Only direct conversations can be joined.'], 422);
        }

        // Idempotent: only announce + stamp the first time.
        if ($conversation->support_joined_at === null) {
            $conversation->support_joined_at = now();
            $conversation->save();

            $conversation->messages()->create([
                'sender_user_id' => $request->user()->id,
                'body' => 'Support has joined this conversation.',
            ]);
        }

        return response()->json(['data' => $conversation->fresh()->load(['space', 'client', 'provider'])]);
    }
}
