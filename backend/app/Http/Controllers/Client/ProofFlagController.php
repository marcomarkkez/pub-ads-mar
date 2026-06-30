<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\Payment;
use App\Models\Proof;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * B9: the CLIENT reviews the provider's proof of display.
 *  - accept  -> payout becomes releasable (Payments releases manually).
 *  - reject  -> payout is HELD and a ticket (with the payment attached) is opened
 *               so Support + Payments can resolve it. Replaces the old
 *               Payments-judges-proof flow.
 */
class ProofFlagController extends Controller
{
    private function ownedProof(Request $request, Proof $proof): Proof
    {
        $proof->loadMissing('booking.payment');
        abort_unless(
            $proof->booking && $proof->booking->client_user_id === $request->user()->id,
            403,
            'You can only review proofs on your own bookings.'
        );

        return $proof;
    }

    /** Client accepts the proof: payout is now releasable by Payments. */
    public function accept(Request $request, Proof $proof): JsonResponse
    {
        $this->ownedProof($request, $proof);

        DB::transaction(function () use ($proof) {
            $proof->update(['status' => 'client_accepted']);

            // Keep the payment in escrow (held) awaiting the manual Payments release.
            $payment = $proof->booking?->payment;
            if ($payment && ! in_array($payment->status, ['released', 'refunded'], true)) {
                $payment->update(['status' => 'held']);
            }
        });

        return response()->json($proof->fresh()->load(['ad', 'booking.space']));
    }

    /** Client rejects the proof: hold the payout + open a Support/Payments ticket. */
    public function reject(Request $request, Proof $proof): JsonResponse
    {
        $this->ownedProof($request, $proof);
        $reason = trim((string) $request->input('reason', ''));

        DB::transaction(function () use ($request, $proof, $reason) {
            $proof->update([
                'status' => 'client_rejected',
                'notes' => trim(($proof->notes ? $proof->notes . ' | ' : '')
                    . 'rejected by client' . ($reason !== '' ? ': ' . $reason : '')),
            ]);

            // Hold the payout until Support + Payments resolve it.
            $payment = $proof->booking?->payment;
            if ($payment && ! in_array($payment->status, ['released', 'refunded'], true)) {
                $payment->update(['status' => 'held']);
            }

            // Attach the ticket to the PAYMENT when there is one (so Payments sees
            // the held money), otherwise to the Ad. Both staff roles see all tickets.
            [$ticketableType, $ticketableId] = $payment
                ? [Payment::class, $payment->id]
                : [Ad::class, $proof->ad_id];

            Ticket::create([
                'user_id' => $request->user()->id,
                'ticketable_type' => $ticketableType,
                'ticketable_id' => $ticketableId,
                'subject' => 'Proof rejected by client — payout held',
                'description' => 'Client rejected proof #' . $proof->id
                    . ($reason !== '' ? '. Reason: ' . $reason : '.')
                    . ' Payout is on hold pending Support/Payments review.',
                'priority' => 'high',
            ]);
        });

        return response()->json($proof->fresh()->load(['ad', 'booking.space']));
    }

    /** Back-compat: the old flag-mismatch action is now just a rejection. */
    public function flagMismatch(Request $request, Proof $proof): JsonResponse
    {
        return $this->reject($request, $proof);
    }
}
