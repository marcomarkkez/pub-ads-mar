<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\PiiMaskingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function index(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        if ($conversation->client_user_id !== $user->id && $conversation->provider_user_id !== $user->id) {
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

        if ($conversation->client_user_id !== $user->id && $conversation->provider_user_id !== $user->id) {
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
