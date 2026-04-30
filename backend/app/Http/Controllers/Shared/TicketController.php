<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tickets = $request->user()->tickets()
            ->with(['ticketable', 'assignedTo'])
            ->latest()
            ->get();

        return response()->json($tickets);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ticketable_type' => 'nullable|in:ad,adset,campaign',
            'ticketable_id' => 'nullable|integer',
            'subject' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'nullable|in:low,medium,high,urgent',
        ]);

        $typeMap = [
            'ad' => 'App\Models\Ad',
            'adset' => 'App\Models\Adset',
            'campaign' => 'App\Models\Campaign',
        ];

        $ticketableType = isset($validated['ticketable_type'])
            ? $typeMap[$validated['ticketable_type']]
            : null;

        $ticket = Ticket::create([
            'user_id' => $request->user()->id,
            'ticketable_type' => $ticketableType,
            'ticketable_id' => $validated['ticketable_id'] ?? null,
            'subject' => $validated['subject'],
            'description' => $validated['description'] ?? null,
            'priority' => $validated['priority'] ?? 'medium',
        ]);

        return response()->json($ticket, 201);
    }

    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        if ($ticket->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json($ticket->load(['ticketable', 'assignedTo', 'messages.user']));
    }

    public function reply(Request $request, Ticket $ticket): JsonResponse
    {
        if ($ticket->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'body' => 'required|string',
        ]);

        $message = $ticket->messages()->create([
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
        ]);

        return response()->json($message->load('user'), 201);
    }
}
