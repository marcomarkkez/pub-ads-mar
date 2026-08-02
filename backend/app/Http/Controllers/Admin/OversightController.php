<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Services\ChatObjectAuthorizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * UC-28 · design.json §10/§12 — the ONE eagle-eye chat oversight screen
 * (GET /admin/oversight/chats). Read-only + incognito: Admin observes EVERY chat
 * (incl. closed, full unmasked history) silently — never posts as a ghost (R1).
 * Replaces the two former oversight views (conversations + tickets).
 */
class OversightController extends Controller
{
    public function __construct(private ChatObjectAuthorizer $authorizer)
    {
    }

    /** UC-28 — list ALL chats with filters ?nature ?flag ?object_type ?object_id ?status ?participant. */
    public function chats(Request $request): JsonResponse
    {
        $query = Chat::query()
            ->with(['client', 'provider', 'opener', 'activeFlags', 'objects.objectable', 'participants.user', 'messages' => function ($q) {
                $q->latest('id')->limit(1);
            }]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('flag')) {
            $query->whereHas('flags', fn ($q) => $q->where('active', true)->where('type', $request->flag));
        }

        if ($request->filled('object_type')) {
            $class = $this->authorizer->resolveClass($request->object_type);
            if ($class) {
                $query->whereHas('objects', function ($q) use ($class, $request) {
                    $q->where('objectable_type', (new $class)->getMorphClass());
                    if ($request->filled('object_id')) {
                        $q->where('objectable_id', $request->object_id);
                    }
                });
            }
        }

        if ($request->filled('participant')) {
            $query->whereHas('participants', fn ($q) => $q->where('user_id', $request->participant));
        }

        $chats = $query->latest()->get();

        // Derived nature is not a column — filter/annotate in-memory.
        $chats = $chats->map(function (Chat $chat) {
            $chat->setAttribute('nature', $chat->deriveNature());

            return $chat;
        });

        if ($request->filled('nature')) {
            $chats = $chats->where('nature', $request->nature)->values();
        }

        return response()->json($chats);
    }

    /**
     * UC-28 — one chat, FULL unmasked history incl. flags + closed chats. Admin
     * bypasses the participant guard and PII masking (the eagle-eye exception).
     */
    public function chat(Chat $chat): JsonResponse
    {
        $chat->load(['client', 'provider', 'opener', 'closedBy', 'flags.createdBy', 'objects.objectable', 'participants.user', 'messages.sender']);
        $chat->setAttribute('nature', $chat->deriveNature());

        return response()->json($chat);
    }
}
