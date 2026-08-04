<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\User;
use App\Models\WalletEntry;
use Illuminate\Support\Facades\DB;

/**
 * design.json §8 — THE refund path. One implementation, two callers.
 *
 * Payments\PaymentController::refund() is the human one (UC-26). UC-29's account
 * FREEZE is the automated one: §12 says a freeze "auto-cancels ALL upcoming bookings
 * on that provider, each with a full refund to the client's wallet", and that refund
 * has to be the SAME money path — same `refund` ledger type, same
 * `refund:payment:{id}` idempotency key, same audit action — or the platform grows a
 * second way to move money that the first one cannot see. A retried freeze would then
 * double-credit a client, because two paths writing two keys is exactly what the
 * unique key exists to prevent.
 *
 * The class holds the two things the callers must not each re-decide:
 *   - refusalFor(): whether this payment may be refunded AT ALL, and
 *   - apply():      how the money and the audit row are written, in one transaction.
 */
class RefundProcessor
{
    /** §8 — one deterministic key per payment; a retry can never double-pay. */
    public static function keyFor(Payment $payment): string
    {
        return "refund:payment:{$payment->id}";
    }

    /**
     * Why this payment cannot be refunded, or null when it can.
     *
     * Shape: ['http' => int, 'body' => array]. 409 for a conflicting STATE (owner
     * 2026-08-03), 422 only for a payment whose payload is structurally broken —
     * a payment with no booking client has nobody to credit, which is malformed
     * data, not a state.
     *
     * @return array{http:int, body:array<string,mixed>}|null
     */
    public function refusalFor(Payment $payment): ?array
    {
        $payment->loadMissing('booking');

        if (! $payment->booking?->client_user_id) {
            return ['http' => 422, 'body' => ['message' => 'Booking client not found.']];
        }

        // §8 — money that has finished moving is not refundable from here. The
        // idempotency key stops a DOUBLE refund, but it never stopped a refund AFTER
        // a release: that credits the client's wallet while the provider has already
        // been paid, so the platform silently eats the amount and no ledger entry says
        // so. Pulling money back from a paid provider is a CLAWBACK — UC-32,
        // POST /admin/payments/{p}/clawback — not a refund.
        if (in_array($payment->status, Payment::TERMINAL, true)) {
            return [
                'http' => 409,
                'body' => [
                    'message' => $payment->status === Payment::STATUS_RELEASED
                        ? 'This payout was already released to the provider. Reversing it is a clawback (UC-32), not a refund.'
                        : 'This payment was already refunded.',
                    'status' => $payment->status,
                ],
            ];
        }

        return null;
    }

    public function canRefund(Payment $payment): bool
    {
        return $this->refusalFor($payment) === null;
    }

    /**
     * Credit the client's wallet and mark the payment refunded, with its audit row,
     * as ONE unit. Callers that are already inside a transaction (the freeze) nest
     * safely — Laravel opens a savepoint.
     *
     * Returns the wallet entry (the existing one on a retry: firstOrCreate on the
     * unique key is what makes the whole operation idempotent).
     */
    public function apply(?User $actor, Payment $payment, string $context): WalletEntry
    {
        $payment->loadMissing('booking');
        $clientId = $payment->booking->client_user_id;
        $amount = (float) $payment->amount;
        $key = self::keyFor($payment);

        return DB::transaction(function () use ($actor, $payment, $clientId, $amount, $key, $context) {
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

            $payment->fill(['status' => Payment::STATUS_REFUNDED]);
            AuditLog::recordChange(
                $actor,
                $payment,
                $context . ' → wallet entry #' . $entry->id,
                'refund',
            );
            $payment->save();

            return $entry;
        });
    }
}
