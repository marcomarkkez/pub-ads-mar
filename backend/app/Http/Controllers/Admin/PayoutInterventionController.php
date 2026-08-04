<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApiErrorCode;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\SystemConfiguration;
use App\Models\WalletEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * UC-32 · design.json §8/§12 — the two money powers Admin has, and the only ones.
 *
 * §8: "Once approved and nothing holds it, **Admin** has a configurable window
 * (default 24 h) to stop it; if Admin doesn't, funds release automatically to the
 * provider." And, for the money that already went out: "Clawback … claw back via
 * negative ledger entry."
 *
 * The two are NOT the same action and must not be reachable from each other:
 *
 *  - STOP is BEFORE the money moves. It is local, free and reversible: it drags the
 *    payout back to `held` so Payments has to make the decision again. No ledger entry
 *    exists, because no money moved.
 *  - CLAWBACK is AFTER the money moved. It cannot be a status flip: the provider HAS
 *    the funds. It writes a NEW negative ledger entry against the provider — its own
 *    `clawback` type, its own `clawback:payment:{id}` idempotency key — and never
 *    touches the original `escrow_release` row. §8 makes `wallet_entries` append-only
 *    and double-entry; netting the reversal into the release would erase the fact that
 *    the provider was ever paid, which is precisely the fact a fraud case turns on.
 *
 * `payments.status` stays the ONLY authority over where the money is (§2/§8). After a
 * clawback the provider no longer holds the funds, so leaving the payment `released`
 * would make the authority lie; it goes back to `held` — the platform holds the money,
 * frozen, pending the next human decision (refund the client, or release again).
 */
class PayoutInterventionController extends Controller
{
    /**
     * GET /admin/payouts — everything Admin may still act on, with the window maths
     * done once here instead of in every UI.
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'payout_stop_hours' => (int) SystemConfiguration::get('payout_stop_hours', 24),
            'payouts' => self::queue(),
        ]);
    }

    /**
     * The payouts Admin can reach: releasable ones (stoppable while the window is
     * open), stopped ones, and released ones (clawback territory).
     *
     * @return list<array<string, mixed>>
     */
    public static function queue(): array
    {
        return Payment::with(['booking.space.user:id,name', 'booking.client:id,name'])
            ->whereIn('status', [Payment::STATUS_FREE_PAYMENT, Payment::STATUS_RELEASED, Payment::STATUS_HELD])
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (Payment $payment) => [
                'id' => $payment->id,
                'amount' => (float) $payment->amount,
                'status' => $payment->status,
                'booking_id' => $payment->booking_id,
                'client' => $payment->booking?->client?->only(['id', 'name']),
                'provider' => $payment->booking?->space?->user?->only(['id', 'name']),
                'payout_releasable_at' => $payment->payout_releasable_at,
                'payout_stop_deadline' => $payment->payoutStopDeadline(),
                'payout_stopped_at' => $payment->payout_stopped_at,
                'clawed_back_at' => $payment->clawed_back_at,
                'can_stop' => $payment->status === Payment::STATUS_FREE_PAYMENT
                    && $payment->isWithinPayoutStopWindow(),
                'can_clawback' => $payment->status === Payment::STATUS_RELEASED,
            ])
            ->all();
    }

    /**
     * POST /admin/payments/{payment}/payout/stop — audit slug `payout_stop`.
     *
     * Refuses with 409 in three distinct states, each with its own message, because
     * "you cannot stop this" is useless to an operator who needs to know whether to
     * wait, to do nothing, or to open a clawback.
     */
    public function stop(Request $request, Payment $payment): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        if (in_array($payment->status, Payment::TERMINAL, true)) {
            return $this->conflict(
                $payment->status === Payment::STATUS_RELEASED
                    ? 'This payout has already been released to the provider. Stopping it is no longer possible — reversing it is a clawback (UC-32).'
                    : 'This payment was already refunded; there is no payout to stop.',
                $payment,
            );
        }

        if ($payment->status !== Payment::STATUS_FREE_PAYMENT) {
            return $this->conflict(
                $payment->status === Payment::STATUS_HELD
                    ? 'This payout is already held — either stopped or frozen in an open dispute.'
                    : 'This payout is not releasable yet, so there is nothing to stop. Only a payout the client has freed (free_payment) can be stopped.',
                $payment,
            );
        }

        // §8 — the window is Admin's whole mandate over a payout. Past it, the payout
        // belongs to Payments again, and the only reversal left is a clawback.
        if (! $payment->isWithinPayoutStopWindow()) {
            return $this->conflict(
                'The ' . (int) SystemConfiguration::get('payout_stop_hours', 24) . 'h admin payout-stop window closed on '
                . $payment->payoutStopDeadline()?->toDateTimeString() . '. Once released, reversing it is a clawback (UC-32).',
                $payment,
            );
        }

        DB::transaction(function () use ($request, $payment, $validated) {
            $payment->fill(['status' => Payment::STATUS_HELD]);
            $payment->payout_stopped_at = now();

            AuditLog::recordChange(
                $request->user(),
                $payment,
                'admin payout stop: ' . $validated['reason'] . ' (§8 · UC-32) — within the '
                . (int) SystemConfiguration::get('payout_stop_hours', 24) . 'h window; no money moved.',
                'payout_stop',
            );

            $payment->save();
        });

        return response()->json($this->payload($payment->fresh()));
    }

    /**
     * POST /admin/payments/{payment}/clawback — audit slug `clawback`.
     *
     * FULL amount only. §8 authorises "a negative ledger entry" and says nothing about
     * partial recovery; a partial clawback needs a policy for the remainder (does the
     * provider keep it? is the client refunded the difference?) that nobody has
     * written, and inventing one here would put an unreviewed money rule in the ledger.
     */
    public function clawback(Request $request, Payment $payment): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        if ($payment->status !== Payment::STATUS_RELEASED) {
            return $this->conflict(
                'Only a payout that was RELEASED to the provider can be clawed back. This payment is '
                . $payment->status . '.',
                $payment,
            );
        }

        $providerId = $payment->loadMissing('booking.space')->booking?->space?->user_id;

        if (! $providerId) {
            // Not a state conflict: there is nobody to debit, so the request is
            // structurally impossible to satisfy (422, per the owner's 2026-08-03 split).
            return response()->json([
                'message' => 'This payment has no provider to claw back from.',
                'payment_id' => $payment->id,
            ], 422);
        }

        $amount = (float) $payment->amount;
        $key = "clawback:payment:{$payment->id}";

        $entry = DB::transaction(function () use ($request, $payment, $providerId, $amount, $key, $validated) {
            $entry = WalletEntry::firstOrCreate(
                ['idempotency_key' => $key],
                [
                    'user_id' => $providerId,
                    // Signed NEGATIVE: the ledger is double-entry and append-only, so a
                    // reversal is a new row that subtracts, never an edit of the release.
                    'amount' => -$amount,
                    'type' => 'clawback',
                    'ref_type' => Payment::class,
                    'ref_id' => $payment->id,
                ]
            );

            $payment->fill(['status' => Payment::STATUS_HELD]);
            $payment->clawed_back_at = now();

            AuditLog::recordChange(
                $request->user(),
                $payment,
                'admin clawback: ' . $validated['reason'] . ' (§8 · UC-32) — '
                . number_format($amount, 2) . ' MXN pulled back from provider #' . $providerId
                . ' → wallet entry #' . $entry->id . '. The original release entry is untouched.',
                'clawback',
            );

            $payment->save();

            return $entry;
        });

        return response()->json([
            'payment' => $this->payload($payment->fresh()),
            'wallet_entry' => [
                'id' => $entry->id,
                'user_id' => $entry->user_id,
                'amount' => (float) $entry->amount,
                'type' => $entry->type,
                'idempotency_key' => $entry->idempotency_key,
            ],
        ]);
    }

    private function conflict(string $message, Payment $payment): JsonResponse
    {
        return response()->json([
            'error_code' => ApiErrorCode::ConflictingState->value,
            'message' => $message,
            'status' => $payment->status,
            'payout_stop_deadline' => $payment->payoutStopDeadline(),
        ], 409);
    }

    private function payload(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'amount' => (float) $payment->amount,
            'status' => $payment->status,
            'payout_releasable_at' => $payment->payout_releasable_at,
            'payout_stop_deadline' => $payment->payoutStopDeadline(),
            'payout_stopped_at' => $payment->payout_stopped_at,
            'clawed_back_at' => $payment->clawed_back_at,
        ];
    }
}
