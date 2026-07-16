<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Payment;
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

        // Return a plain array (matches the client/shared ticket lists and the
        // frontend support queue which sets the signal directly from the body).
        $tickets = $query->latest()->get();

        return response()->json($tickets);
    }

    public function show(Ticket $ticket): JsonResponse
    {
        $ticket->load(['user', 'assignedTo', 'ticketable', 'messages.user']);

        // design.md §11 [F09]: surface the client↔provider conversation behind a
        // payment/booking ticket so Support can open + join it from here, plus the
        // Support-anchored dispute threads (§7/§10) keyed by type.
        $data = $ticket->toArray();
        $threads = $this->relatedConversations($ticket);
        $data['related_conversation_id'] = $threads[Conversation::TYPE_DIRECT] ?? null;
        $data['dispute_conversations'] = [
            'internal' => $threads[Conversation::TYPE_INTERNAL] ?? null,
            'support_client' => $threads[Conversation::TYPE_SUPPORT_CLIENT] ?? null,
            'support_provider' => $threads[Conversation::TYPE_SUPPORT_PROVIDER] ?? null,
        ];

        return response()->json($data);
    }

    /**
     * Map of thread type => conversation id for the booking behind this ticket.
     */
    private function relatedConversations(Ticket $ticket): array
    {
        $t = $ticket->ticketable;
        $booking = $t instanceof Payment ? $t->booking : ($t instanceof Booking ? $t : null);

        if (! $booking || ! $booking->space_id || ! $booking->client_user_id) {
            return [];
        }

        return Conversation::where('space_id', $booking->space_id)
            ->where('client_user_id', $booking->client_user_id)
            ->pluck('id', 'type')
            ->toArray();
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
