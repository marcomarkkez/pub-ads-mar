<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\WalletEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Every method that moves money is a `$` action in the §2 matrix, so it is
 * 📝-logged (UC-31): the audit row is written in the SAME transaction as the
 * state change, from the still-dirty model, so a rolled-back payment leaves no
 * entry and a written entry always has a matching state change.
 */
class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // UC-27 · design.json §10/§8 — load the dispute chats (with their active flags)
        // this payment is attached to, so Payments sees WHY money is held.
        $query = Payment::with(['booking.client', 'booking.space', 'approvedBy', 'chatObjects.chat.activeFlags']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $payments = $query->latest()->paginate(20);

        return response()->json($payments);
    }

    public function show(Payment $payment): JsonResponse
    {
        return response()->json($payment->load(['booking.client', 'booking.space', 'booking.ad', 'approvedBy', 'chatObjects.chat.activeFlags']));
    }

    public function approve(Request $request, Payment $payment): JsonResponse
    {
        $this->settle($request, $payment, 'approve', [
            'approved_by_payments' => true,
            'approved_by_user_id' => $request->user()->id,
            'status' => 'completed',
        ]);

        return response()->json($payment->load('approvedBy'));
    }

    public function reject(Request $request, Payment $payment): JsonResponse
    {
        $this->settle($request, $payment, 'reject', [
            'approved_by_payments' => false,
            'approved_by_user_id' => $request->user()->id,
            'status' => 'failed',
        ]);

        return response()->json($payment->load('approvedBy'));
    }

    /**
     * Apply a money decision and log it as one unit. recordChange() reads the
     * dirty attributes, so it has to run BEFORE save() — and returns null when
     * the decision changes nothing, which is not an action worth a row.
     */
    private function settle(Request $request, Payment $payment, string $action, array $changes): void
    {
        $payment->fill($changes);

        DB::transaction(function () use ($request, $payment, $action) {
            AuditLog::recordChange(
                $request->user(),
                $payment,
                'payments ' . $action . ' (§2 $ · §8)',
                $action,
            );
            $payment->save();
        });
    }

    /**
     * Refund a payment (mocked gateway): credit the client's wallet and mark refunded.
     * Idempotent via a deterministic idempotency_key on the unique wallet entry.
     */
    public function refund(Request $request, Payment $payment): JsonResponse
    {
        $payment->loadMissing('booking');
        $clientId = $payment->booking?->client_user_id;

        if (! $clientId) {
            return response()->json(['message' => 'Booking client not found.'], 422);
        }

        $amount = (float) $payment->amount;
        $key = "refund:payment:{$payment->id}";

        DB::transaction(function () use ($request, $payment, $clientId, $amount, $key) {
            $entry = WalletEntry::firstOrCreate(
                ['idempotency_key' => $key],
                [
                    'user_id' => $clientId,
                    'amount' => $amount,
                    'type' => 'refund',
                    'ref_type' => Payment::class,
                    'ref_id' => $payment->id,
                ]
            );

            $payment->fill(['status' => 'refunded']);
            AuditLog::recordChange(
                $request->user(),
                $payment,
                'payments refund → wallet entry #' . $entry->id . ' (§2 $)',
                'refund',
            );
            $payment->save();
        });

        return response()->json($payment->fresh()->load('approvedBy'));
    }

    /**
     * Release the payout to the provider: mark released + escrow_release wallet entry.
     */
    public function releasePayout(Request $request, Payment $payment): JsonResponse
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

        DB::transaction(function () use ($request, $payment, $providerId, $amount, $key) {
            $entry = null;

            if ($providerId) {
                $entry = WalletEntry::firstOrCreate(
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

            $payment->fill(['status' => 'released']);
            AuditLog::recordChange(
                $request->user(),
                $payment,
                'payments release payout' . ($entry ? ' → wallet entry #' . $entry->id : ' (no provider on the space)') . ' (§2 $)',
                'release_payout',
            );
            $payment->save();
        });

        return response()->json($payment->fresh()->load('approvedBy'));
    }

    /**
     * Hold the payout (escrow): mark held. Mocked gateway, no funds move.
     */
    public function holdPayout(Request $request, Payment $payment): JsonResponse
    {
        // Don't drag a settled payment back into escrow — a released or refunded
        // payment is terminal (mirrors accept/reject + Support's flagPayoutHold).
        if (! in_array($payment->status, ['released', 'refunded'], true)) {
            $this->settle($request, $payment, 'hold_payout', ['status' => 'held']);
        }

        return response()->json($payment->fresh()->load('approvedBy'));
    }
}
