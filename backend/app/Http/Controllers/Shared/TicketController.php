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
            'ticketable_type' => 'nullable|in:ad,adset,campaign,space',
            'ticketable_id' => 'nullable|integer',
            'subject' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'nullable|in:low,medium,high,urgent',
        ]);

        $typeMap = [
            'ad' => \App\Models\Ad::class,
            'adset' => \App\Models\Adset::class,
            'campaign' => \App\Models\Campaign::class,
            'space' => \App\Models\Space::class,
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

        $ticket->load(['ticketable', 'assignedTo', 'messages.user']);

        // C11 guard: only staff (admin/support/payments) may see internal notes.
        if (! in_array($request->user()->role, ['admin', 'support', 'payments'], true)) {
            $ticket->setRelation(
                'messages',
                $ticket->messages->reject(fn ($m) => $m->is_internal)->values()
            );
        }

        return response()->json($ticket);
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

        // Frontend (shared ticket-detail) expects { data: message }.
        return response()->json(['data' => $message->load('user')], 201);
    }
}
