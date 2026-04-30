<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Ticket::with(['user', 'assignedTo', 'ticketable']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        $tickets = $query->latest()->paginate(20);

        return response()->json($tickets);
    }

    public function show(Ticket $ticket): JsonResponse
    {
        return response()->json($ticket->load(['user', 'assignedTo', 'ticketable', 'messages.user']));
    }

    public function update(Request $request, Ticket $ticket): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'sometimes|in:open,in_progress,waiting_user,resolved,closed',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'assigned_to_user_id' => 'nullable|exists:users,id',
        ]);

        $ticket->update($validated);

        return response()->json($ticket->load(['user', 'assignedTo']));
    }

    public function reply(Request $request, Ticket $ticket): JsonResponse
    {
        $validated = $request->validate([
            'body' => 'required|string',
            'is_internal' => 'nullable|boolean',
        ]);

        $message = $ticket->messages()->create([
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
            'is_internal' => $validated['is_internal'] ?? false,
        ]);

        return response()->json($message->load('user'), 201);
    }
}
