<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApiErrorCode;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\RolePermission;
use App\Models\Space;
use App\Models\SystemConfiguration;
use App\Models\User;
use App\Services\RefundProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * UC-29 · design.json §12 — Admin moderation. The ONLY writes Admin has; everything
 * else about the platform it may read and nothing more (§12: "Admin is the eagle-eye
 * role"). Every action here writes one AuditLog row with a verb slug, in the SAME
 * transaction as the state change.
 *
 * §12 names THREE levels of taking a listing/provider "off" and this controller
 * exists to keep them APART:
 *
 *  1. PAUSE / UNPUBLISH — the provider's own `is_active` toggle, reversible by them.
 *     Not here. Admin does not pause listings.
 *  2. TAKEDOWN / RESTORE — admin moderation on ONE listing. "The provider CANNOT
 *     self-reverse": the state lives in `spaces.taken_down_at`, which is not fillable
 *     and has no provider-facing route, and Provider\SpaceController refuses a
 *     re-publish while it is set (409). A provider flipping `is_active` back on
 *     therefore changes their own level and leaves this one exactly where it was.
 *  3. FREEZE / UNFREEZE — admin moderation on an ACCOUNT. Stops NEW bookings, cancels
 *     the upcoming ones with a full refund each, revokes the sessions.
 *
 * Admin never posts in a chat (§10/§16 R1) — a freeze opens no conversation with the
 * provider and this controller has no message path at all.
 */
class ModerationController extends Controller
{
    public function __construct(private readonly RefundProcessor $refunds) {}

    /**
     * The moderation console feed (§12 "each indicator links to a list"): the listings
     * and accounts moderation can act on, plus the payouts UC-32 can still stop.
     * Read-only.
     */
    public function index(Request $request): JsonResponse
    {
        $spaces = Space::with('user:id,name,email,role,frozen_at')
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (Space $space) => [
                'id' => $space->id,
                'name' => $space->name,
                'location_text' => $space->location_text,
                'provider' => $space->user?->only(['id', 'name', 'email']),
                'is_active' => (bool) $space->is_active,
                'taken_down_at' => $space->taken_down_at,
                'takedown_reason' => $space->takedown_reason,
                // The one derived answer the UI must not re-invent per screen.
                'bookable' => (bool) $space->is_active
                    && ! $space->isTakenDown()
                    && ! ($space->user?->isFrozen() ?? false),
            ]);

        $providers = User::where('role', 'provider')
            ->withCount('spaces')
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => (bool) $user->is_active,
                'frozen_at' => $user->frozen_at,
                'freeze_reason' => $user->freeze_reason,
                'spaces_count' => $user->spaces_count,
            ]);

        return response()->json([
            'spaces' => $spaces,
            'providers' => $providers,
            // UC-32 lives on the same console: the admin who freezes an account is the
            // one who then has to decide about that provider's money (§8/§12).
            'payout_stop_hours' => (int) SystemConfiguration::get('payout_stop_hours', 24),
            'payouts' => PayoutInterventionController::queue(),
        ]);
    }

    /** POST /admin/spaces/{space}/takedown — audit slug `takedown`. */
    public function takedown(Request $request, Space $space): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        if ($space->isTakenDown()) {
            return $this->conflict(
                'This listing is already taken down.',
                ['space_id' => $space->id, 'taken_down_at' => $space->taken_down_at],
            );
        }

        DB::transaction(function () use ($request, $space, $validated) {
            $space->taken_down_at = now();
            $space->takedown_reason = $validated['reason'];

            AuditLog::recordChange(
                $request->user(),
                $space,
                'admin takedown: ' . $validated['reason'] . ' (§12 · UC-29). The provider cannot reverse this.',
                'takedown',
            );

            $space->save();
        });

        return response()->json($this->spacePayload($space->fresh()));
    }

    /** POST /admin/spaces/{space}/restore — audit slug `restore`. */
    public function restore(Request $request, Space $space): JsonResponse
    {
        if (! $space->isTakenDown()) {
            return $this->conflict(
                'This listing is not under a takedown; there is nothing to restore.',
                ['space_id' => $space->id],
            );
        }

        DB::transaction(function () use ($request, $space) {
            $space->taken_down_at = null;
            $space->takedown_reason = null;

            AuditLog::recordChange(
                $request->user(),
                $space,
                'admin restore — moderation lifted (§12 · UC-29). The provider\'s own pause, if any, still applies.',
                'restore',
            );

            $space->save();
        });

        // Restoring lifts LEVEL 2 only. If the provider had also paused the listing
        // (level 1), it stays hidden — theirs to reverse, not admin's.
        return response()->json($this->spacePayload($space->fresh()));
    }

    /**
     * POST /admin/providers/{user}/freeze — audit slug `freeze`.
     *
     * §12: "pauses NEW bookings AND auto-cancels ALL upcoming bookings on that
     * provider, each with a full refund to the client's wallet. A freeze busts that
     * user's permission/session cache immediately and revokes active Sanctum tokens."
     */
    public function freeze(Request $request, User $user): JsonResponse
    {
        $this->assertProvider($user);

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        if ($user->isFrozen()) {
            return $this->conflict(
                'This account is already frozen.',
                ['user_id' => $user->id, 'frozen_at' => $user->frozen_at],
            );
        }

        $outcome = DB::transaction(function () use ($request, $user, $validated) {
            $user->frozen_at = now();
            $user->freeze_reason = $validated['reason'];

            AuditLog::recordChange(
                $request->user(),
                $user,
                'admin freeze: ' . $validated['reason'] . ' (§12 · UC-29) — new bookings paused, upcoming bookings cancelled and refunded, sessions revoked.',
                'freeze',
            );

            $user->save();

            return $this->cancelUpcomingBookings($request, $user);
        });

        // Outside the txn on purpose: a rolled-back freeze must not have logged
        // anybody out, and a token deletion cannot be rolled back into a live session.
        $user->tokens()->delete();
        RolePermission::clearCache($user->role);

        return response()->json([
            'user' => $this->userPayload($user->fresh()),
            'cancelled_bookings' => $outcome['cancelled'],
            'refunds' => $outcome['refunded'],
            // Payouts already RELEASED cannot be refunded — reversing one is a
            // clawback (UC-32), an explicit, separately-audited admin decision.
            'not_refunded' => $outcome['skipped'],
        ]);
    }

    /** POST /admin/providers/{user}/unfreeze — audit slug `unfreeze`. */
    public function unfreeze(Request $request, User $user): JsonResponse
    {
        $this->assertProvider($user);

        if (! $user->isFrozen()) {
            return $this->conflict(
                'This account is not frozen.',
                ['user_id' => $user->id],
            );
        }

        DB::transaction(function () use ($request, $user) {
            $user->frozen_at = null;
            $user->freeze_reason = null;

            AuditLog::recordChange(
                $request->user(),
                $user,
                'admin unfreeze (§12 · UC-29). Cancelled bookings are NOT restored — they were refunded.',
                'unfreeze',
            );

            $user->save();
        });

        return response()->json($this->userPayload($user->fresh()));
    }

    /**
     * §12 — "auto-cancels ALL upcoming bookings on that provider, each with a full
     * refund to the client's wallet."
     *
     * UPCOMING is read as "has not started yet AND is not already over": a booking
     * whose display is currently RUNNING (active / waiting_proof) is not upcoming, and
     * cancelling it would destroy the record a proof and a payout are argued from.
     * Those keep their existing money state and are listed back to the caller.
     *
     * The refund goes through App\Services\RefundProcessor — the same path
     * Payments\PaymentController::refund uses, same `refund:payment:{id}` key — so a
     * client can never be credited twice for one payment, whichever path ran first.
     *
     * @return array{cancelled:list<int>, refunded:list<array<string,mixed>>, skipped:list<array<string,mixed>>}
     */
    private function cancelUpcomingBookings(Request $request, User $provider): array
    {
        $bookings = Booking::whereIn('space_id', $provider->spaces()->select('id'))
            ->whereIn('status', Booking::UPCOMING_STATUSES)
            ->whereDate('start_date', '>=', now()->toDateString())
            ->with('payment')
            ->get();

        $cancelled = [];
        $refunded = [];
        $skipped = [];

        foreach ($bookings as $booking) {
            $booking->fill(['status' => 'cancelled']);
            AuditLog::recordChange(
                $request->user(),
                $booking,
                'auto-cancelled by the freeze of provider #' . $provider->id . ' (§12 · UC-29)',
                'cancel',
            );
            $booking->save();
            $cancelled[] = $booking->id;

            $payment = $booking->payment;

            if (! $payment) {
                continue;
            }

            if ($refusal = $this->refunds->refusalFor($payment)) {
                $skipped[] = [
                    'payment_id' => $payment->id,
                    'status' => $payment->status,
                    'reason' => $refusal['body']['message'],
                ];

                continue;
            }

            $entry = $this->refunds->apply(
                $request->user(),
                $payment,
                'full refund on the freeze of provider #' . $provider->id . ' (§12 · UC-29)',
            );

            $refunded[] = [
                'payment_id' => $payment->id,
                'wallet_entry_id' => $entry->id,
                'amount' => (float) $entry->amount,
            ];
        }

        return ['cancelled' => $cancelled, 'refunded' => $refunded, 'skipped' => $skipped];
    }

    /**
     * The /admin/providers/{u} path addresses PROVIDERS. A client or a staff user is
     * not "a provider that may not be frozen", it is not in that collection at all —
     * so 404, not 403 and not 409. (A client account has no listings to freeze; §12
     * defines the freeze on the provider side.)
     */
    private function assertProvider(User $user): void
    {
        abort_unless($user->isProvider(), 404, 'Not found.');
    }

    /** 409 for a conflicting STATE — never 422, which stays for malformed payloads. */
    private function conflict(string $message, array $extra = []): JsonResponse
    {
        return response()->json(array_merge([
            'error_code' => ApiErrorCode::ConflictingState->value,
            'message' => $message,
        ], $extra), 409);
    }

    private function spacePayload(Space $space): array
    {
        return [
            'id' => $space->id,
            'name' => $space->name,
            'is_active' => (bool) $space->is_active,
            'taken_down_at' => $space->taken_down_at,
            'takedown_reason' => $space->takedown_reason,
        ];
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => (bool) $user->is_active,
            'frozen_at' => $user->frozen_at,
            'freeze_reason' => $user->freeze_reason,
        ];
    }
}
