<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * design.json §3 (AC-accounts-01/02) — the Account, a first-class object.
 *
 * "Every owner user — a client or a provider — has exactly ONE account; for MVP
 *  an account IS that single owner user (1 user = 1 account)."
 *
 * The 1:1 is a UNIQUE INDEX on users.account_id, so the owner→account resolver is
 * just `$user->account` and the account→owner resolver is `$account->owner`.
 * There is deliberately no service, no registry and no lookup layer around that:
 * under 1:1 there is nothing for one to do, and an abstraction the MVP cannot
 * exercise is an abstraction nobody can verify.
 */
class Account extends Model
{
    public const TYPE_CLIENT = 'client';

    public const TYPE_PROVIDER = 'provider';

    /** Normal. The account's listings may appear in the catalog. */
    public const PUBLICATION_PUBLISHED = 'published';

    /**
     * AD-delguard-09 · UC-37 — out of circulation as a CONSEQUENCE: a deletion is
     * programmed on this account. Read by Space::scopeBookable(), so every listing the
     * account owns leaves the catalog from this one column.
     */
    public const PUBLICATION_UNPUBLISHED = 'unpublished';

    /**
     * `publication_status`, `delete_scheduled_at` and `proof_loss_confirmed_at` are NOT
     * fillable, and that is the guarantee: no request payload can put an account into —
     * or out of — a programmed deletion. Only AccountController and accounts:purge move
     * them, by naming the attribute.
     */
    protected $fillable = [
        'name',
        'type',
    ];

    protected function casts(): array
    {
        return [
            'delete_scheduled_at' => 'datetime',
            'proof_loss_confirmed_at' => 'datetime',
        ];
    }

    /**
     * The account's owner user. hasOne, not belongsTo: the FK lives on `users`
     * (owner 2026-08-01) precisely so this can become hasMany without a schema
     * change the day an account has several owners.
     */
    public function owner(): HasOne
    {
        return $this->hasOne(User::class);
    }

    /** Every owner user of this account — exactly one for the whole MVP. */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    /** §3 — collaborators hang off the ACCOUNT, never off a campaign or a space. */
    public function collaborators(): HasMany
    {
        return $this->hasMany(Collaborator::class);
    }

    /** A deletion is programmed on this account (AD-delguard-09 · UC-37). */
    public function isScheduledForDeletion(): bool
    {
        return $this->delete_scheduled_at !== null;
    }

    public function isUnpublished(): bool
    {
        return $this->publication_status === self::PUBLICATION_UNPUBLISHED;
    }

    // ── The two guardrails (AD-delguard-09 · §3/§12 · UC-37) ────────────────────
    //
    // Owner 2026-08-04: "si el dueño de la cuenta es el proveedor, borrarse a sí mismo
    // destruye la evidencia de disputas que quizá afecten a clientes que ya pagaron."
    //
    // The old rule let the owner destroy their own proofs on a `confirm_proof_loss`
    // flag. That confirmation protects the owner from THEIR OWN decision — it says "I
    // know I will not be paid without these". It cannot speak for the client on the
    // other side of a dispute, who is a party to the same record and never consented.
    // So the blockers split in two, by WHOSE loss they are:
    //
    //   disputeBlockers()  the counterparty's evidence. ABSOLUTE — no flag clears it,
    //                      nothing is scheduled, nothing is unpublished, 409.
    //   inUseBlockers()    the platform's own record and live obligations. Deletion
    //                      becomes a DATE: unpublish now, destroy later, if still clean.

    /**
     * Evidence in a LIVE, two-sided disagreement. Non-empty = this account cannot be
     * deleted at all, today or in six weeks, until the dispute closes.
     *
     * A list and not a boolean, for the same reason Space::deletionBlockers() is: a
     * refusal that cannot say what is blocking it leaves the owner with no move.
     */
    public function disputeBlockers(): array
    {
        $owner = $this->owner;

        if ($owner === null) {
            return [];
        }

        $blockers = [];

        // 1. An ACTIVE dispute flag on a chat this account is a side of. The flag is
        //    the annotation, never the authority — but "somebody raised payment_held
        //    and nobody superseded it" is exactly the open-dispute marker (§10/§15).
        $disputedChats = Chat::query()
            ->where(fn ($q) => $q->where('client_user_id', $owner->id)->orWhere('provider_user_id', $owner->id))
            ->whereHas('flags', fn ($q) => $q->where('active', true)->whereIn('type', ChatFlag::DISPUTE_TYPES))
            ->count();

        if ($disputedChats > 0) {
            $blockers[] = [
                'kind' => 'open_dispute',
                'count' => $disputedChats,
                'message' => $disputedChats . ' chat(s) on this account carry an open dispute flag. '
                    . 'The conversation and its evidence belong to the other party too and cannot be deleted while it is open.',
            ];
        }

        // 2. Money frozen inside a dispute. `payments.status` is the authority over
        //    where the money is (§8), and `held` means it is contested, not merely late.
        $heldPayments = Payment::query()
            ->where('status', Payment::STATUS_HELD)
            ->whereIn('booking_id', $this->bookingIdsQuery($owner))
            ->count();

        if ($heldPayments > 0) {
            $blockers[] = [
                'kind' => 'held_payments',
                'count' => $heldPayments,
                'message' => $heldPayments . ' payment(s) are held inside an open dispute.',
            ];
        }

        // 3. A rejected proof IS the disputed item: the client said the display did not
        //    happen as agreed, and the file is what the argument is about. Counted both
        //    where this owner uploaded it and where it hangs off a booking they are a
        //    party to — a provider must not delete the proof a client rejected, and a
        //    client must not delete the proof they themselves rejected.
        $rejectedProofs = Proof::query()
            ->where('status', Proof::STATUS_CLIENT_REJECTED)
            ->where(fn ($q) => $q
                ->where('uploaded_by_user_id', $owner->id)
                ->orWhereIn('booking_id', $this->bookingIdsQuery($owner)))
            ->count();

        if ($rejectedProofs > 0) {
            $blockers[] = [
                'kind' => 'rejected_proofs',
                'count' => $rejectedProofs,
                'message' => $rejectedProofs . ' proof(s) were rejected by the client and are evidence in that dispute.',
            ];
        }

        return $blockers;
    }

    /**
     * Objects that are in use or that the platform must keep. Non-empty = the deletion
     * is PROGRAMMED instead of performed; empty = the account can go now.
     *
     * This set deliberately mirrors the §3 `ON DELETE RESTRICT` guardrails
     * (campaigns/spaces/bookings/wallet_entries) rather than being cleverer than them:
     * the purge must never issue a DELETE the engine will refuse.
     */
    public function inUseBlockers(): array
    {
        $owner = $this->owner;

        if ($owner === null) {
            return [];
        }

        $liveBookings = Booking::query()
            ->whereIn('status', Booking::LIVE_STATUSES)
            ->whereIn('id', $this->bookingIdsQuery($owner))
            ->count();

        $unsettledPayments = Payment::query()
            ->whereIn('status', Payment::UNSETTLED_STATUSES)
            ->whereIn('booking_id', $this->bookingIdsQuery($owner))
            ->count();

        // `bookings.client_user_id` is RESTRICT, so a FINISHED booking still pins the
        // owner row. Counted separately from the live ones so the two messages do not
        // overlap and neither number is a lie.
        $pastBookings = Booking::query()
            ->where('client_user_id', $owner->id)
            ->whereNotIn('status', Booking::LIVE_STATUSES)
            ->count();

        $candidates = [
            ['kind' => 'live_bookings', 'count' => $liveBookings,
                'message' => $liveBookings . ' booking(s) on this account are still live.'],
            ['kind' => 'unsettled_payments', 'count' => $unsettledPayments,
                'message' => $unsettledPayments . ' payment(s) on this account have not finished settling.'],
            ['kind' => 'campaigns', 'count' => Campaign::where('user_id', $owner->id)->count(),
                'message' => 'campaign(s) still belong to this account.'],
            ['kind' => 'spaces', 'count' => Space::where('user_id', $owner->id)->count(),
                'message' => 'listing(s) still belong to this account.'],
            ['kind' => 'past_bookings', 'count' => $pastBookings,
                'message' => 'past booking(s) are part of the platform\'s record and cannot be orphaned.'],
            ['kind' => 'wallet_entries', 'count' => WalletEntry::where('user_id', $owner->id)->count(),
                'message' => 'wallet ledger entries belong to this account. A ledger that can be deleted is not a ledger.'],
        ];

        $blockers = [];

        foreach ($candidates as $candidate) {
            if ($candidate['count'] > 0) {
                // The counted kinds already carry their number in the sentence; the flat
                // ones get it prefixed, so every message reads as one whole sentence.
                if (! str_starts_with($candidate['message'], (string) $candidate['count'])) {
                    $candidate['message'] = $candidate['count'] . ' ' . $candidate['message'];
                }

                $blockers[] = $candidate;
            }
        }

        return $blockers;
    }

    /**
     * Everything that stands between this account and destruction, disputes first.
     * This is what `accounts:purge` re-asks at purge time — weeks after the schedule
     * was programmed, and about a different world.
     */
    public function deletionBlockers(): array
    {
        return array_merge($this->disputeBlockers(), $this->inUseBlockers());
    }

    /** Proofs the OWNER uploaded — the only ones their confirmation can speak for. */
    public function ownerProofCount(): int
    {
        return $this->owner === null
            ? 0
            : Proof::where('uploaded_by_user_id', $this->owner->id)->count();
    }

    /**
     * Every booking this account is a party to, from EITHER side: the ones it made as
     * a client, and the ones made on the listings it owns as a provider. A dispute has
     * two sides and the guardrail has to see both of them.
     */
    private function bookingIdsQuery(User $owner)
    {
        return Booking::query()
            ->select('id')
            ->where(fn ($q) => $q
                ->where('client_user_id', $owner->id)
                ->orWhereIn('space_id', Space::query()->select('id')->where('user_id', $owner->id)));
    }

    /**
     * §3 — "Every user gets an account at registration." Called from the
     * User::created hook, so registration, the admin's user CRUD, the seeders and
     * every factory in the test suite all get one for free and none of them can
     * forget. Staff roles get NULL: `type` is client|provider and a staff user
     * owns no campaigns or spaces.
     */
    public static function provisionFor(User $user): ?self
    {
        if ($user->account_id !== null || ! in_array($user->role, [self::TYPE_CLIENT, self::TYPE_PROVIDER], true)) {
            return null;
        }

        $account = static::create([
            'name' => $user->company_name ?: $user->name,
            'type' => $user->role,
        ]);

        $user->forceFill(['account_id' => $account->id])->saveQuietly();

        return $account;
    }
}
