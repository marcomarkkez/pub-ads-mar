<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * UC-28-adjacent · design.md §10 — Support stats dashboard. A "ticket" is now a
 * chat with a Support participant, so the queue is DERIVED: staff-facing chats
 * (nature != client↔provider) plus client↔provider chats Support has joined.
 */
class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Support's queue: staff-facing chats (either side anchor null) + joined chats.
        $queue = Chat::query()->where(function ($q) {
            $q->whereNull('client_user_id')
                ->orWhereNull('provider_user_id')
                ->orWhereNotNull('support_joined_at');
        });

        $countBy = fn (array $statuses) => (clone $queue)->whereIn('status', $statuses)->count();

        return response()->json([
            'waiting_review' => $countBy([Chat::STATUS_OPEN, Chat::STATUS_IN_PROGRESS]),
            'open'           => $countBy([Chat::STATUS_OPEN]),
            'in_progress'    => $countBy([Chat::STATUS_IN_PROGRESS]),
            'solved'         => $countBy([Chat::STATUS_RESOLVED, Chat::STATUS_CLOSED]),
            'total'          => (clone $queue)->count(),
        ]);
    }
}
