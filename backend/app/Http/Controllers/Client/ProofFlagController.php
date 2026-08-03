<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Chat;
use App\Models\ChatFlag;
use App\Models\ChatParticipant;
use App\Models\Payment;
use App\Models\Proof;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * UC-6/UC-7 · design.json §7/§10 — the CLIENT reviews the provider's proof of display.
 *  - accept (UC-6) → payout becomes releasable (Payments releases manually).
 *  - reject (UC-7) → payout is HELD, a `payment_held` flag is raised on the internal
 *                    Support↔Payments chat, and THREE dispute chats auto-open
 *                    (Support↔Client / ↔Provider / ↔Payments), each anchored to the
 *                    held payment+booking. No automation beyond hold + flag + 3 chats.
 */
class ProofFlagController extends Controller
{
    private function ownedProof(Request $request, Proof $proof): Proof
    {
        $proof->loadMissing('booking.payment');
        // 404, never 403 — §21 rule 2 (Q37): a 403 confirms the row exists, which is enough to
        // enumerate other clients' proof ids. A stranger must not be able to tell the difference
        // between "not yours" and "does not exist".
        abort_unless(
            $proof->booking && $proof->booking->client_user_id === $request->user()->id,
            404,
            'Not found.'
        );

        return $proof;
    }

    /**
     * Q46 · §2 — park a payout AND leave a trace. The actor here is the CLIENT, not staff, which
     * is exactly why it needs auditing: it is the one path where a non-staff user moves money.
     * Both accept and reject go through here — auditing half of a symmetric pair is worse than
     * auditing neither, because the gap reads as "it never happened".
     */
    private function auditedHold(Request $request, Payment $payment, string $action, string $context): void
    {
        $before = ['status' => $payment->status];
        $payment->update(['status' => 'held']);

        AuditLog::record($request->user(), $action, $payment, $before, ['status' => 'held'], $context);
    }

    /** UC-6 · Client accepts the proof: payout is now releasable by Payments. */
    public function accept(Request $request, Proof $proof): JsonResponse
    {
        $this->ownedProof($request, $proof);

        DB::transaction(function () use ($request, $proof) {
            $proof->update(['status' => 'client_accepted']);

            $payment = $proof->booking?->payment;
            if ($payment && ! in_array($payment->status, ['released', 'refunded'], true)) {
                $this->auditedHold($request, $payment, 'hold_on_accept',
                    'Client accepted proof #' . $proof->id . ' → payout held until Payments releases (§7 · UC-6)');
            }
        });

        return response()->json($proof->fresh()->load(['ad', 'booking.space']));
    }

    /**
     * UC-7 · design.json §7/§10 — client rejects the proof: HOLD the payout and hand
     * the case to a 100%-human Support review by opening the three dispute chats.
     */
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

            $payment = $proof->booking?->payment;
            if ($payment && ! in_array($payment->status, ['released', 'refunded'], true)) {
                $this->auditedHold($request, $payment, 'hold_on_reject',
                    'Client rejected proof #' . $proof->id . ' → payout held pending Support review (§7 · UC-7)');
            }

            $this->openDisputeChats($request, $proof, $reason);
        });

        return response()->json($proof->fresh()->load(['ad', 'booking.space']));
    }

    /**
     * UC-7 · design.json §7/§10 — open the three dispute chats (idempotent). Each is an
     * instance of the ONE chat primitive, anchored to the held payment+booking with a
     * `payment_held` flag + a system opener; they differ only by participant set:
     *   - internal          Support ↔ Payments  (both anchors null)
     *   - support_client    Support ↔ Client    (client anchor only)
     *   - support_provider  Support ↔ Provider  (provider anchor only)
     * Idempotency = "an OPEN chat already attached to THIS payment/booking with THIS
     * participant set" — not a `type` unique key (retired).
     */
    private function openDisputeChats(Request $request, Proof $proof, string $reason): void
    {
        $booking = $proof->booking;
        $space = $booking?->space;

        if (! $booking) {
            return;
        }

        $payment = $booking->payment;
        $clientId = $booking->client_user_id;
        $providerId = $space?->user_id;
        $openerId = $request->user()->id;

        $opener = 'Dispute opened: client rejected proof #' . $proof->id
            . ($reason !== '' ? '. Reason: ' . $reason : '.')
            . ' Payout is held pending Support review.';

        // The stable idempotency anchor: the payment when present, else the booking.
        $anchor = $payment ?: $booking;

        $sets = [
            ['client' => null, 'provider' => null, 'side' => null],                        // internal
            ['client' => $clientId, 'provider' => null, 'side' => ChatParticipant::SIDE_CLIENT], // support_client
            ['client' => null, 'provider' => $providerId, 'side' => ChatParticipant::SIDE_PROVIDER], // support_provider
        ];

        foreach ($sets as $set) {
            if ($this->existingDisputeChat($set['client'], $set['provider'], $anchor)) {
                continue; // idempotent: this participant set already open for this payment
            }

            $chat = Chat::create([
                'opened_by_user_id' => $openerId,
                'client_user_id' => $set['client'],
                'provider_user_id' => $set['provider'],
                'status' => Chat::STATUS_OPEN,
                'last_message_at' => now(),
            ]);

            // Only the counterparty gets a participant row; Support/Payments are
            // role-derived (do NOT enumerate them, and never add the opener to the
            // internal Support↔Payments chat — that would leak it to the client).
            if ($set['side'] === ChatParticipant::SIDE_CLIENT && $clientId) {
                $chat->participants()->create(['user_id' => $clientId, 'side' => ChatParticipant::SIDE_CLIENT, 'joined_at' => now()]);
            } elseif ($set['side'] === ChatParticipant::SIDE_PROVIDER && $providerId) {
                $chat->participants()->create(['user_id' => $providerId, 'side' => ChatParticipant::SIDE_PROVIDER, 'joined_at' => now()]);
            }

            // Attach the held payment (if any) + the booking as chat_objects.
            if ($payment) {
                $this->attach($chat, $payment, $openerId);
            }
            $this->attach($chat, $booking, $openerId);

            // Raise the payment_held flag (annotates the WHY; money state stays on payments.status).
            $chat->flags()->create([
                'type' => ChatFlag::TYPE_PAYMENT_HELD,
                'reason' => $reason !== '' ? $reason : 'Client rejected proof #' . $proof->id,
                'active' => true,
                'created_by_user_id' => $openerId,
            ]);

            // One-time system opener.
            $chat->messages()->create([
                'sender_user_id' => $openerId,
                'body' => $opener,
                'kind' => 'system',
            ]);
        }
    }

    private function existingDisputeChat(?int $clientId, ?int $providerId, Model $anchor): bool
    {
        return Chat::query()
            ->where('status', '!=', Chat::STATUS_CLOSED)
            ->where(fn (Builder $q) => $clientId === null ? $q->whereNull('client_user_id') : $q->where('client_user_id', $clientId))
            ->where(fn (Builder $q) => $providerId === null ? $q->whereNull('provider_user_id') : $q->where('provider_user_id', $providerId))
            ->whereHas('objects', fn (Builder $q) => $q
                ->where('objectable_type', $anchor->getMorphClass())
                ->where('objectable_id', $anchor->getKey()))
            ->exists();
    }

    private function attach(Chat $chat, Model $object, int $userId): void
    {
        $chat->objects()->create([
            'objectable_type' => $object->getMorphClass(),
            'objectable_id' => $object->getKey(),
            'attached_by_user_id' => $userId,
        ]);
    }

    /** Back-compat: the old flag-mismatch action is now just a rejection (UC-7). */
    public function flagMismatch(Request $request, Proof $proof): JsonResponse
    {
        return $this->reject($request, $proof);
    }
}
