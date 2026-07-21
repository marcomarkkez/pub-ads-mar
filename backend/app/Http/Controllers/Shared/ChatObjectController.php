<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\ChatObject;
use App\Services\ChatObjectAuthorizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * UC-8 · design.md §10/§16 (R2) — attach/detach polymorphic objects on a chat via
 * the two-dropdown UI. Attach is OWNERSHIP-CHECKED: client/provider only own objects,
 * Support/Payments/Admin any. Each change writes a kind=system message.
 */
class ChatObjectController extends Controller
{
    public function __construct(private ChatObjectAuthorizer $authorizer)
    {
    }

    public function attach(Request $request, Chat $chat): JsonResponse
    {
        $user = $request->user();

        if (! $chat->userCanAccess($user)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'object_type' => 'required|in:ad,adset,campaign,space,payment,booking',
            'object_id' => 'required|integer',
        ]);

        $class = $this->authorizer->resolveClass($validated['object_type']);
        $object = $class ? $class::find($validated['object_id']) : null;

        if (! $object) {
            return response()->json(['message' => 'Object not found.'], 404);
        }

        // R2: a chat never exposes an object its attacher could not already see.
        if (! $this->authorizer->canAttach($user, $object)) {
            return response()->json(['message' => 'You cannot attach that object.'], 403);
        }

        DB::transaction(function () use ($chat, $object, $user) {
            $chat->objects()->create([
                'objectable_type' => $object->getMorphClass(),
                'objectable_id' => $object->getKey(),
                'attached_by_user_id' => $user->id,
            ]);

            $chat->messages()->create([
                'sender_user_id' => $user->id,
                'body' => class_basename($object) . ' #' . $object->getKey() . ' attached to this chat.',
                'kind' => 'system',
            ]);
            $chat->update(['last_message_at' => now()]);
        });

        return response()->json(['data' => $chat->fresh()->load('objects.objectable')], 201);
    }

    public function detach(Request $request, Chat $chat, ChatObject $object): JsonResponse
    {
        $user = $request->user();

        // Detach is rare — Support/Admin housekeeping; anyone with chat access may
        // detach an object they could attach.
        if (! $chat->userCanAccess($user) || $object->chat_id !== $chat->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        DB::transaction(function () use ($chat, $object, $user) {
            $label = class_basename($object->objectable_type) . ' #' . $object->objectable_id;
            $object->delete();

            $chat->messages()->create([
                'sender_user_id' => $user->id,
                'body' => $label . ' detached from this chat.',
                'kind' => 'system',
            ]);
            $chat->update(['last_message_at' => now()]);
        });

        return response()->json(['data' => $chat->fresh()->load('objects.objectable')]);
    }
}
