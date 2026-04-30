<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Space;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $conversations = Conversation::where('client_user_id', $user->id)
            ->orWhere('provider_user_id', $user->id)
            ->with(['space', 'client', 'provider', 'messages' => function ($q) {
                $q->latest()->limit(1);
            }])
            ->latest()
            ->get();

        return response()->json($conversations);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'space_id' => 'required|exists:spaces,id',
        ]);

        $space = Space::findOrFail($validated['space_id']);

        // Only clients can start conversations
        if ($request->user()->role !== 'client') {
            return response()->json(['message' => 'Only clients can start conversations.'], 403);
        }

        $conversation = Conversation::firstOrCreate(
            [
                'space_id' => $space->id,
                'client_user_id' => $request->user()->id,
            ],
            [
                'provider_user_id' => $space->user_id,
            ]
        );

        return response()->json($conversation->load(['space', 'client', 'provider']), 201);
    }
}
