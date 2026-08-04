<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\WalletEntry;
use App\Services\RefundProcessor;
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
        if ($refusal = $this->refuseIfLocked($payment, 'approve')) {
            return $refusal;
        }

        $this->settle($request, $payment, 'approve', [
            'approved_by_payments' => true,
            'approved_by_user_id' => $request->user()->id,
            'status' => Payment::STATUS_COMPLETED,
        ]);

        return response()->json($payment->load('approvedBy'));
    }

    public function reject(Request $request, Payment $payment): JsonResponse
    {
        if ($refusal = $this->refuseIfLocked($payment, 'reject')) {
            return $refusal;
        }

        $this->settle($request, $payment, 'reject', [
            'approved_by_payments' => false,
            'approved_by_user_id' => $request->user()->id,
            'status' => Payment::STATUS_FAILED,
        ]);

        return response()->json($payment->load('approvedBy'));
    }

    /**
     * §8 — the state guard `settle()` never had, while `holdPayout()` did.
     *
     * Without it, a Payments user could take a payment that was frozen by a client's proof
     * rejection — three dispute chats open, `payment_held` flag active — and flip it to
     * `completed` with one click. The chats kept saying "payout held"; `payments.status`,
     * which §2 makes the authority, said the opposite. Nothing errored and nothing logged
     * a conflict, because as far as the code was concerned nothing unusual had happened.
     *
     * 409 and not 422 (owner 2026-08-03): the payload is fine — it is the payment's state
     * that forbids the move. To settle a disputed payment, resolve the dispute first.
     */
    private function refuseIfLocked(Payment $payment, string $action): ?JsonResponse
    {
        if (! in_array($payment->status, Payment::LOCKED_AGAINST_SETTLE, true)) {
            return null;
        }

        return response()->json([
            'message' => $payment->status === Payment::STATUS_HELD
                ? 'This payout is held in an open dispute. Resolve the dispute before settling it.'
                : 'This payment is already ' . $payment->status . ' and cannot be settled again.',
            'status' => $payment->status,
            'attempted' => $action,
        ], 409);
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
     *
     * The decision ("may this be refunded?") and the money ("credit + audit in one
     * txn") live in App\Services\RefundProcessor, because UC-29's account freeze
     * auto-refunds through the SAME path (§12). This method is the human entry point
     * to it and nothing more — a second refund implementation is a second source of
     * truth about money.
     */
    public function refund(Request $request, Payment $payment, RefundProcessor $refunds): JsonResponse
    {
        if ($refusal = $refunds->refusalFor($payment)) {
            return response()->json($refusal['body'], $refusal['http']);
        }

        $refunds->apply($request->user(), $payment, 'payments refund (§2 $)');

        return response()->json($payment->fresh()->load('approvedBy'));
    }

    /**
     * Release the payout to the provider: mark released + escrow_release wallet entry.
     */
    public function releasePayout(Request $request, Payment $payment): JsonResponse
    {
        $payment->loadMissing('booking.space');

        // B9 gate, now read off the payment itself. It used to re-derive the client's verdict
        // from booking.proofs on every call — the authority over money living in a table that
        // holds none. `free_payment` IS "the client accepted", written once by the client's
        // own accept, so a held payment now fails this for the right reason instead of
        // accidentally passing because some other proof on the booking was accepted.
        if ($payment->status !== Payment::STATUS_FREE_PAYMENT) {
            return response()->json([
                'message' => $payment->status === Payment::STATUS_HELD
                    ? 'This payout is held in an open dispute and cannot be released.'
                    : 'Payout can only be released after the client accepts the proof of display.',
                'status' => $payment->status,
                'required_status' => Payment::STATUS_FREE_PAYMENT,
            ], 409);
        }

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

            $payment->fill(['status' => Payment::STATUS_RELEASED]);
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
        if (! in_array($payment->status, Payment::TERMINAL, true)) {
            $this->settle($request, $payment, 'hold_payout', ['status' => Payment::STATUS_HELD]);
        }

        return response()->json($payment->fresh()->load('approvedBy'));
    }
}
