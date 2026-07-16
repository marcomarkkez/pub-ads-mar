<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\PiiMaskingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    // design.md §10 [F08]: internal Support↔Payments threads are never visible to
    // clients/providers — membership-based ACL, not a flag on a shared thread.
    private const STAFF_ROLES = ['admin', 'support', 'payments'];

    private function forbidsAccess(Conversation $conversation, $user): bool
    {
        $type = $conversation->type ?? Conversation::TYPE_DIRECT;

        // Internal Support↔Payments threads: only staff (admin/support/payments).
        if ($type === Conversation::TYPE_INTERNAL) {
            return !in_array($user->role, self::STAFF_ROLES, true);
        }

        // Support-anchored dispute threads (design.md §7/§10/§11): Support + Admin,
        // PLUS the single counterparty (the client for support_client, the provider
        // for support_provider). Nobody else — Payments works via the internal thread.
        if ($type === Conversation::TYPE_SUPPORT_CLIENT) {
            if ($conversation->client_user_id === $user->id) {
                return false;
            }

            return !in_array($user->role, ['support', 'admin'], true);
        }

        if ($type === Conversation::TYPE_SUPPORT_PROVIDER) {
            if ($conversation->provider_user_id === $user->id) {
                return false;
            }

            return !in_array($user->role, ['support', 'admin'], true);
        }

        // Direct client↔provider threads: the two participants always.
        if ($conversation->client_user_id === $user->id
            || $conversation->provider_user_id === $user->id) {
            return false;
        }

        // Support may read/post ONCE it has joined (announced) — design.md §11 [F09]
        // / §10 [F08]. Payments does NOT read these (it works via the internal
        // Support↔Payments thread; owner 2026-07-14); Admin reads via the read-only
        // oversight endpoints, not here.
        if ($user->role === 'support' && ($conversation->support_joined_at ?? null) !== null) {
            return false;
        }

        return true;
    }

    public function index(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        if ($this->forbidsAccess($conversation, $user)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        // Mark messages from other party as read
        $conversation->messages()
            ->where('sender_user_id', '!=', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $masker = app(PiiMaskingService::class);

        $messages = $conversation->messages()->with('sender')->oldest()->get()
            ->map(function ($message) use ($masker, $user, $conversation) {
                $message->body = $masker->mask($message->body, $user, $conversation);

                return $message;
            });

        // Frontend (conversation-detail) expects { data: { conversation, messages } }.
        return response()->json([
            'data' => [
                'conversation' => $conversation->load(['space', 'client', 'provider']),
                'messages' => $messages,
            ],
        ]);
    }

    public function store(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        if ($this->forbidsAccess($conversation, $user)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'body' => 'required|string|max:5000',
        ]);

        // C06: persist the RAW body — PII is masked at render time (index)
        // by the PiiMaskingService so admins can still read originals.
        $message = $conversation->messages()->create([
            'sender_user_id' => $user->id,
            'body' => $validated['body'],
        ]);

        // Frontend (conversation-detail) expects { data: message }.
        return response()->json(['data' => $message->load('sender')], 201);
    }
}
