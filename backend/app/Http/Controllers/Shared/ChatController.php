<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\ChatObject;
use App\Models\ChatParticipant;
use App\Services\ChatObjectAuthorizer;
use App\Services\PiiMaskingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * UC-8/UC-9/UC-21 · design.md §10/§16/§17 — the ONE Messages surface. Nature + ACL
 * are DERIVED from participants+objects (never a `type` column, never a flag).
 * Admin never reaches this controller (read-only/incognito via oversight — R1).
 */
class ChatController extends Controller
{
    public function __construct(private ChatObjectAuthorizer $authorizer)
    {
    }

    /** UC-8/UC-9/UC-27 — the caller's Messages list (derived queue). */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $chats = Chat::query()
            ->visibleTo($user)
            ->with(['client', 'provider', 'activeFlags', 'objects.objectable', 'participants', 'messages' => function ($q) {
                $q->latest('id')->limit(1);
            }])
            ->orderByRaw('COALESCE(last_message_at, created_at) DESC')
            ->get()
            ->map(fn (Chat $chat) => $this->decorate($chat));

        return response()->json($chats);
    }

    /**
     * UC-8/UC-9 — open a chat (objectless = contact support; object-attached = inquiry).
     * Only a client or provider opens via this endpoint; Support enters via join/dispute.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! in_array($user->role, ['client', 'provider'], true)) {
            return response()->json(['message' => 'Only clients and providers can open a chat.'], 403);
        }

        $validated = $request->validate([
            'object_type' => 'nullable|in:ad,adset,campaign,space,payment,booking',
            'object_id' => 'nullable|integer',
            'provider_id' => 'nullable|exists:users,id',
            // UC-8/UC-9 · design.md §10 — the ENTRY POINT sets the counterparty (support = default;
            // provider only from a published listing). Billing routes to support (option B).
            'target' => 'nullable|in:support,provider',
            'body' => 'nullable|string|max:5000',
        ]);

        // Resolve the attached object (if any) and ownership-check it up front (R2).
        $object = null;
        if (! empty($validated['object_type']) && ! empty($validated['object_id'])) {
            $class = $this->authorizer->resolveClass($validated['object_type']);
            $object = $class ? $class::find($validated['object_id']) : null;

            if (! $object) {
                return response()->json(['message' => 'Object not found.'], 404);
            }
            if (! $this->authorizer->canAttach($user, $object)) {
                return response()->json(['message' => 'You cannot attach that object.'], 403);
            }
        }

        // Derive the two side anchors from the actor + object/provider.
        $clientId = null;
        $providerId = null;

        if ($user->isClient()) {
            $clientId = $user->id;
            // UC-8/UC-9 · design.md §10 — the ENTRY POINT sets the counterparty, NOT the attached
            // object. Default = Support (support_client); the object is context only and does NOT
            // pull in the provider. A provider inquiry is reached ONLY from a published listing,
            // which sends target=provider (+ the space object, or an explicit provider_id).
            $providerId = null;
            if (($validated['target'] ?? null) === 'provider') {
                $providerId = $validated['provider_id']
                    ?? ($object ? $this->authorizer->providerFor($object) : null);
            }
        } else { // provider
            $providerId = $user->id;
            // A provider-opened chat is provider↔support unless a client is implied.
            $clientId = null;
        }

        $chat = DB::transaction(function () use ($user, $clientId, $providerId, $object, $validated) {
            $chat = Chat::create([
                'opened_by_user_id' => $user->id,
                'client_user_id' => $clientId,
                'provider_user_id' => $providerId,
                'status' => Chat::STATUS_OPEN,
            ]);

            $chat->participants()->create([
                'user_id' => $user->id,
                'side' => $user->isClient() ? ChatParticipant::SIDE_CLIENT : ChatParticipant::SIDE_PROVIDER,
                'joined_at' => now(),
            ]);

            if ($object) {
                $this->attachObjectRow($chat, $object, $user->id);
                $this->systemMessage($chat, $user->id, 'Chat opened with '
                    . class_basename($object) . ' #' . $object->getKey() . ' attached.');
            }

            if (! empty($validated['body'])) {
                $this->userMessage($chat, $user->id, $validated['body']);
            }

            return $chat;
        });

        return response()->json($this->decorate($chat->fresh()), 201);
    }

    /** UC-8/UC-9 — full chat (participants + objects + active flags + masked messages). */
    public function show(Request $request, Chat $chat): JsonResponse
    {
        $user = $request->user();

        if (! $chat->userCanAccess($user)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        // A closed chat's history is hidden from normal users (visible to Admin via
        // oversight only; Admin never reaches this controller) — design.md §10.
        if ($chat->isClosed()) {
            return response()->json([
                'data' => [
                    'chat' => $this->decorate($chat),
                    'messages' => [],
                    'closed' => true,
                ],
            ]);
        }

        $chat->messages()
            ->where('sender_user_id', '!=', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $masker = app(PiiMaskingService::class);

        $messages = $chat->messages()->with('sender')->oldest('id')->get()
            ->map(function ($message) use ($masker, $user, $chat) {
                $message->body = $masker->mask($message->body, $user, $chat);

                return $message;
            });

        return response()->json([
            'data' => [
                'chat' => $this->decorate($chat),
                'messages' => $messages,
            ],
        ]);
    }

    /** UC-8/UC-9 — post a message (raw stored, masked at render); same derived ACL. */
    public function postMessage(Request $request, Chat $chat): JsonResponse
    {
        $user = $request->user();

        if (! $chat->userCanAccess($user)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        // A closed chat is read-only — users open a NEW chat instead (design.md §10).
        if ($chat->isClosed()) {
            return response()->json(['message' => 'This chat is closed.'], 422);
        }

        $validated = $request->validate(['body' => 'required|string|max:5000']);

        $message = $this->userMessage($chat, $user->id, $validated['body']);

        return response()->json(['data' => $message->load('sender')], 201);
    }

    /**
     * UC-21 · design.md §10 — Support joins a client↔provider chat: creates an
     * announced support participant, stamps support_joined_at (PII relax) and writes
     * a system message. Idempotent. role:support (Admin never joins — incognito).
     */
    public function join(Request $request, Chat $chat): JsonResponse
    {
        if ($chat->deriveNature() !== Chat::NATURE_CLIENT_PROVIDER) {
            return response()->json(['message' => 'Only client↔provider chats can be joined.'], 422);
        }

        $user = $request->user();

        if ($chat->support_joined_at === null) {
            DB::transaction(function () use ($chat, $user) {
                $chat->update(['support_joined_at' => now()]);

                $chat->participants()->updateOrCreate(
                    ['user_id' => $user->id],
                    ['side' => ChatParticipant::SIDE_SUPPORT, 'announced' => true, 'joined_at' => now(), 'left_at' => null]
                );

                $this->systemMessage($chat, $user->id, 'Support has joined this conversation.');
            });
        }

        return response()->json(['data' => $this->decorate($chat->fresh())]);
    }

    /** UC-22/UC-23 · design.md §10 — Support marks the chat resolved (did its part). */
    public function resolve(Request $request, Chat $chat): JsonResponse
    {
        $user = $request->user();

        if (! in_array($user->role, ['support', 'payments'], true)) {
            return response()->json(['message' => 'Only staff can resolve a chat.'], 403);
        }
        if (! $chat->userCanAccess($user)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $chat->update(['status' => Chat::STATUS_RESOLVED, 'resolved_at' => now()]);
        $this->systemMessage($chat, $user->id, 'Chat marked resolved by support.');

        return response()->json(['data' => $this->decorate($chat->fresh())]);
    }

    /**
     * UC-8/UC-9 · design.md §10 — close a chat. The OPENER confirms it is solved;
     * a staff force-close is an Admin-style override that writes a system event.
     */
    public function close(Request $request, Chat $chat): JsonResponse
    {
        $user = $request->user();
        $isOpener = $chat->opened_by_user_id === $user->id;
        $isStaff = in_array($user->role, ['support', 'payments'], true);

        if (! $isOpener && ! $isStaff) {
            return response()->json(['message' => 'Only the opener or staff can close a chat.'], 403);
        }
        if (! $isStaff && ! $chat->userCanAccess($user)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $chat->update([
            'status' => Chat::STATUS_CLOSED,
            'closed_at' => now(),
            'closed_by_user_id' => $user->id,
        ]);
        $this->systemMessage($chat, $user->id, $isOpener
            ? 'Chat closed by the opener.'
            : 'Chat force-closed by staff.');

        return response()->json(['data' => $this->decorate($chat->fresh())]);
    }

    /**
     * UC-28 · design.md §10 — reopen a closed chat. ADMIN ONLY (investigation);
     * users open a NEW chat instead. Gated by role:admin at the route.
     */
    public function reopen(Request $request, Chat $chat): JsonResponse
    {
        if (! $chat->isClosed()) {
            return response()->json(['message' => 'Chat is not closed.'], 422);
        }

        $chat->update([
            'status' => Chat::STATUS_OPEN,
            'closed_at' => null,
            'closed_by_user_id' => null,
        ]);
        $this->systemMessage($chat, $request->user()->id, 'Chat reopened by admin for investigation.');

        return response()->json(['data' => $this->decorate($chat->fresh())]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function decorate(Chat $chat): Chat
    {
        $chat->loadMissing(['client', 'provider', 'activeFlags', 'objects.objectable', 'participants.user']);
        $chat->setAttribute('nature', $chat->deriveNature());
        // UI collapses states to Abierto/Cerrado (design.md §10).
        $chat->setAttribute('ui_status', $chat->isClosed() ? 'Cerrado' : 'Abierto');

        return $chat;
    }

    private function attachObjectRow(Chat $chat, $object, int $userId): ChatObject
    {
        return $chat->objects()->create([
            'objectable_type' => $object->getMorphClass(),
            'objectable_id' => $object->getKey(),
            'attached_by_user_id' => $userId,
        ]);
    }

    private function userMessage(Chat $chat, int $senderId, string $body)
    {
        $message = $chat->messages()->create([
            'sender_user_id' => $senderId,
            'body' => $body,
            'kind' => 'user',
        ]);
        $chat->update(['last_message_at' => now()]);

        return $message;
    }

    private function systemMessage(Chat $chat, int $senderId, string $body): void
    {
        $chat->messages()->create([
            'sender_user_id' => $senderId,
            'body' => $body,
            'kind' => 'system',
        ]);
        $chat->update(['last_message_at' => now()]);
    }
}
