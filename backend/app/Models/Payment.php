<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Payment extends Model
{
    /**
     * §8 — `payments.status` is the ONLY authority over where the money is. A chat flag
     * annotates why; it never decides.
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /** Client accepted the proof: the payout is releasable and waiting on Payments. */
    public const STATUS_FREE_PAYMENT = 'free_payment';

    /** Frozen inside a live dispute. NOT the same as "waiting to be paid". */
    public const STATUS_HELD = 'held';

    public const STATUS_RELEASED = 'released';

    public const STATUS_REFUNDED = 'refunded';

    /** The money has finished moving; nothing may drag it back. */
    public const TERMINAL = [self::STATUS_RELEASED, self::STATUS_REFUNDED];

    /**
     * States a Payments approve/reject must NOT overwrite. `held` is in here and the two
     * terminals are not enough on their own: a held payment is the visible half of an open
     * dispute (3 chats + an active payment_held flag), so silently flipping it to
     * `completed` would leave the chats saying "payout held" while the authority says paid.
     */
    public const LOCKED_AGAINST_SETTLE = [self::STATUS_HELD, self::STATUS_RELEASED, self::STATUS_REFUNDED];

    protected $fillable = [
        'booking_id',
        'amount',
        'status',
        'payment_method',
        'payment_platform',
        'transaction_id',
        'approved_by_payments',
        'approved_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'approved_by_payments' => 'boolean',
            'payout_releasable_at' => 'datetime',
            'payout_stopped_at' => 'datetime',
            'clawed_back_at' => 'datetime',
        ];
    }

    /**
     * UC-32 · §8 — the end of the Admin payout-stop window, or null when the window
     * never opened (the payout is not releasable yet, so there is nothing to stop).
     *
     * The window is `payout_stop_hours` (System Config, default 24) counted from the
     * moment the payout became RELEASABLE, not from the release: §8 gives Admin time
     * to intervene BEFORE the money goes out. Once the money has gone out the reversal
     * is a clawback, which has no timer at all.
     */
    public function payoutStopDeadline(): ?\Illuminate\Support\Carbon
    {
        if ($this->payout_releasable_at === null) {
            return null;
        }

        return $this->payout_releasable_at->copy()
            ->addHours((int) SystemConfiguration::get('payout_stop_hours', 24));
    }

    /** True while Admin may still stop this payout (§8, UC-32). */
    public function isWithinPayoutStopWindow(): bool
    {
        $deadline = $this->payoutStopDeadline();

        return $deadline !== null && now()->lessThanOrEqualTo($deadline);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /**
     * UC-7/UC-27 · design.json §10 — chats this payment is attached to (dispute /
     * internal Support↔Payments chats). This is how Payments sees WHY money is held
     * and what Support requested — the retired `tickets` morphMany is now chat_objects.
     */
    public function chatObjects(): MorphMany
    {
        return $this->morphMany(ChatObject::class, 'objectable');
    }
}
