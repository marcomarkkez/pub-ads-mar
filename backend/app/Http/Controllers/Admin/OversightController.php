<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;

class OversightController extends Controller
{
    /**
     * Eagle-eye (C11): list ALL conversations in the system with no participant
     * filter, each with a latest-message preview. Admin reads silently/incognito.
     */
    public function conversations(): JsonResponse
    {
        $conversations = Conversation::query()
            ->with([
                'space',
                'client',
                'provider',
                'messages' => function ($q) {
                    $q->latest()->limit(1);
                },
            ])
            ->latest()
            ->get();

        return response()->json($conversations);
    }

    /**
     * Eagle-eye (C11): return ALL messages for ANY conversation — admin bypasses
     * the participant guard enforced in Shared\MessageController. Raw bodies are
     * returned unmasked (admin is the eagle-eye exception to PII masking).
     */
    public function conversationMessages(Conversation $conversation): JsonResponse
    {
        $messages = $conversation->messages()
            ->with('sender')
            ->oldest()
            ->get();

        return response()->json($messages);
    }

    /**
     * Eagle-eye (C11): list ALL tickets in the system with their referenced object.
     */
    public function tickets(): JsonResponse
    {
        $tickets = Ticket::query()
            ->with(['ticketable', 'user', 'assignedTo'])
            ->latest()
            ->get();

        return response()->json($tickets);
    }

    /**
     * Eagle-eye (C11): return a ticket with ALL of its messages INCLUDING
     * is_internal=true ones. Admin sees the private Support<->Payments notes that
     * the Shared\TicketController hides from non-staff viewers.
     */
    public function ticket(Ticket $ticket): JsonResponse
    {
        $ticket->load(['ticketable', 'user', 'assignedTo', 'messages.user']);

        return response()->json($ticket);
    }
}
