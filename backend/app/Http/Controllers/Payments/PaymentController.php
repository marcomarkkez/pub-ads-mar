<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\WalletEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Load the Support flags/tickets so Payments sees WHY money is held
        // and what was requested (design.md §11 [F09] -> §8 [F10]).
        $query = Payment::with(['booking.client', 'booking.space', 'approvedBy', 'tickets']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $payments = $query->latest()->paginate(20);

        return response()->json($payments);
    }

    public function show(Payment $payment): JsonResponse
    {
        return response()->json($payment->load(['booking.client', 'booking.space', 'booking.ad', 'approvedBy', 'tickets']));
    }

    public function approve(Request $request, Payment $payment): JsonResponse
    {
        $payment->update([
            'approved_by_payments' => true,
            'approved_by_user_id' => $request->user()->id,
            'status' => 'completed',
        ]);

        return response()->json($payment->load('approvedBy'));
    }

    public function reject(Request $request, Payment $payment): JsonResponse
    {
        $payment->update([
            'approved_by_payments' => false,
            'approved_by_user_id' => $request->user()->id,
            'status' => 'failed',
        ]);

        return response()->json($payment->load('approvedBy'));
    }

    /**
     * Refund a payment (mocked gateway): credit the client's wallet and mark refunded.
     * Idempotent via a deterministic idempotency_key on the unique wallet entry.
     */
    public function refund(Payment $payment): JsonResponse
    {
        $payment->loadMissing('booking');
        $clientId = $payment->booking?->client_user_id;

        if (! $clientId) {
            return response()->json(['message' => 'Booking client not found.'], 422);
        }

        $amount = (float) $payment->amount;
        $key = "refund:payment:{$payment->id}";

        DB::transaction(function () use ($payment, $clientId, $amount, $key) {
            WalletEntry::firstOrCreate(
                ['idempotency_key' => $key],
                [
                    'user_id' => $clientId,
                    'amount' => $amount,
                    'type' => 'refund',
                    'ref_type' => Payment::class,
                    'ref_id' => $payment->id,
                ]
            );

            $payment->update(['status' => 'refunded']);
        });

        return response()->json($payment->fresh()->load('approvedBy'));
    }

    /**
     * Release the payout to the provider: mark released + escrow_release wallet entry.
     */
    public function releasePayout(Payment $payment): JsonResponse
    {
        $payment->loadMissing('booking.space', 'booking.proofs');

        // B9 gate: payout only releases after the CLIENT accepted the proof.
        $clientAccepted = $payment->booking?->proofs
            ?->contains(fn ($p) => $p->status === 'client_accepted') ?? false;

        abort_unless(
            $clientAccepted,
            422,
            'Payout can only be released after the client accepts the proof of display.'
        );

        $providerId = $payment->booking?->space?->user_id;

        $amount = (float) $payment->amount;
        $key = "escrow_release:payment:{$payment->id}";

        DB::transaction(function () use ($payment, $providerId, $amount, $key) {
            if ($providerId) {
                WalletEntry::firstOrCreate(
                    ['idempotency_key' => $key],
                    [
                        'user_id' => $providerId,
                        'amount' => $amount,
                        'type' => 'escrow_release',
                        'ref_type' => Payment::class,
                        'ref_id' => $payment->id,
                    ]
                );
            }

            $payment->update(['status' => 'released']);
        });

        return response()->json($payment->fresh()->load('approvedBy'));
    }

    /**
     * Hold the payout (escrow): mark held. Mocked gateway, no funds move.
     */
    public function holdPayout(Payment $payment): JsonResponse
    {
        $payment->update(['status' => 'held']);

        return response()->json($payment->fresh()->load('approvedBy'));
    }
}
