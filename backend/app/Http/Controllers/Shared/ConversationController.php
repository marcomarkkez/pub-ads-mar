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

        // Each party sees their own direct client↔provider threads, plus their own
        // Support-anchored dispute thread (design.md §7/§10). Internal Support↔Payments
        // and the other party's dispute thread are never listed here.
        $conversations = Conversation::query()
            ->where(function ($q) use ($user) {
                $q->where('type', Conversation::TYPE_DIRECT)
                    ->where(function ($qq) use ($user) {
                        $qq->where('client_user_id', $user->id)
                            ->orWhere('provider_user_id', $user->id);
                    });
            })
            ->orWhere(function ($q) use ($user) {
                $q->where('type', Conversation::TYPE_SUPPORT_CLIENT)
                    ->where('client_user_id', $user->id);
            })
            ->orWhere(function ($q) use ($user) {
                $q->where('type', Conversation::TYPE_SUPPORT_PROVIDER)
                    ->where('provider_user_id', $user->id);
            })
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

        // Pin the match to the DIRECT thread: a space+client may also carry
        // Support-anchored dispute threads that share the same space+client.
        $conversation = Conversation::firstOrCreate(
            [
                'space_id' => $space->id,
                'client_user_id' => $request->user()->id,
                'type' => Conversation::TYPE_DIRECT,
            ],
            [
                'provider_user_id' => $space->user_id,
            ]
        );

        return response()->json($conversation->load(['space', 'client', 'provider']), 201);
    }
}
