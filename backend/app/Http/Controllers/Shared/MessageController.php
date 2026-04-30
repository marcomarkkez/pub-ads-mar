<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
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

        return response()->json($conversation->messages()->with('sender')->oldest()->get());
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

        // Filter contact info from message body
        $body = $this->filterContactInfo($validated['body']);

        $message = $conversation->messages()->create([
            'sender_user_id' => $user->id,
            'body' => $body,
        ]);

        return response()->json($message->load('sender'), 201);
    }

    private function filterContactInfo(string $body): string
    {
        // Strip phone numbers (various formats)
        $body = preg_replace('/(\+?\d{1,3}[-.\s]?)?\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}/', '[phone removed]', $body);

        // Strip email addresses
        $body = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', '[email removed]', $body);

        // Strip URLs
        $body = preg_replace('/https?:\/\/[^\s]+/', '[link removed]', $body);
        $body = preg_replace('/www\.[^\s]+/', '[link removed]', $body);

        return $body;
    }
}
