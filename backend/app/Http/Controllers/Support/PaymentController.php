<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * design.md §11 [F09]: Support has NO money authority — it only FLAGS a refund
 * or a payout-hold for PAYMENTS to decide and execute. A flag is recorded as a
 * ticket attached to the payment (ticketable = payment), the same channel
 * Payments already watches; payout-hold additionally parks the money in `held`.
 */
class PaymentController extends Controller
{
    public function flagRefund(Request $request, Payment $payment): JsonResponse
    {
        $reason = $this->reason($request);

        $ticket = Ticket::create([
            'user_id' => $request->user()->id,
            'ticketable_type' => Payment::class,
            'ticketable_id' => $payment->id,
            'subject' => 'Refund flagged by Support',
            'description' => 'Support flagged this payment for a REFUND. Payments decides + executes.'
                . ($reason ? " Reason: {$reason}" : ''),
            'status' => 'open',
            'priority' => 'high',
        ]);

        return response()->json(['data' => $ticket], 201);
    }

    public function flagPayoutHold(Request $request, Payment $payment): JsonResponse
    {
        $reason = $this->reason($request);

        // Park the money in `held` so Payments sees it in their held queue.
        if (! in_array($payment->status, ['released', 'refunded'], true)) {
            $payment->update(['status' => 'held']);
        }

        $ticket = Ticket::create([
            'user_id' => $request->user()->id,
            'ticketable_type' => Payment::class,
            'ticketable_id' => $payment->id,
            'subject' => 'Payout hold flagged by Support',
            'description' => 'Support flagged this payout to be HELD. Payments decides + executes.'
                . ($reason ? " Reason: {$reason}" : ''),
            'status' => 'open',
            'priority' => 'high',
        ]);

        return response()->json(['data' => $ticket, 'payment' => $payment->fresh()], 201);
    }

    private function reason(Request $request): ?string
    {
        $validated = $request->validate(['reason' => 'nullable|string|max:1000']);

        return $validated['reason'] ?? null;
    }
}
