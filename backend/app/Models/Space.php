<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Space extends Model
{
    /** Live and visible in the catalog. */
    public const PUBLICATION_PUBLISHED = 'published';

    /** The PROVIDER's own pause — their lever, reversible by them (§12 level 1). */
    public const PUBLICATION_PAUSED = 'paused';

    /**
     * AD-delguard-08 · UC-37 — out of circulation as a CONSEQUENCE, not as a choice:
     * a deletion is programmed on this listing. Owner 2026-08-03, asked what a
     * listing queued for deletion becomes: "Unpublished". Its own value, never
     * `is_active = false`, because un-pausing must not silently un-queue a deletion.
     */
    public const PUBLICATION_UNPUBLISHED = 'unpublished';

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'latitude',
        'longitude',
        'price_per_day',
        'price_per_month',
        'pricing_unit',
        'description',
        'location_text',
        'width',
        'height',
        'ical_url',
        'calendar_keyword',
        'is_active',
    ];

    /**
     * UC-29 · §12 — `taken_down_at` and `takedown_reason` are NOT fillable, and that
     * is the whole guarantee: the provider's own space PUT, Support's edit-any PUT
     * and every future mass-assignment run through `$fillable`, so none of them can
     * clear an admin takedown even by accident. Only Admin\ModerationController
     * writes them, by naming the attribute directly.
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'price_per_day' => 'decimal:2',
            'price_per_month' => 'decimal:2',
            'width' => 'decimal:2',
            'height' => 'decimal:2',
            'is_active' => 'boolean',
            'taken_down_at' => 'datetime',
            'delete_scheduled_at' => 'datetime',
        ];
    }

    /**
     * `is_active` (the provider's lever) and `publication_status` (what the catalog reads)
     * are two views of one fact, and they must never drift. Keeping them in step HERE rather
     * than in each controller is the point: a future endpoint that flips `is_active` cannot
     * forget, and a provider un-pausing a listing cannot silently un-queue its deletion —
     * `unpublished` is deliberately absent from the mapping, so it survives the lever.
     */
    protected static function booted(): void
    {
        static::saving(function (self $space) {
            if ($space->publication_status === self::PUBLICATION_UNPUBLISHED) {
                return;
            }

            // `?? true` mirrors the column default: on INSERT the attribute is often unset
            // and the database supplies `true` afterwards, so reading a bare null here
            // would file every freshly created listing as `paused`.
            $space->publication_status = ($space->is_active ?? true)
                ? self::PUBLICATION_PUBLISHED
                : self::PUBLICATION_PAUSED;
        });
    }

    /** A deletion is programmed on this listing (AD-delguard-08 · UC-37). */
    public function isScheduledForDeletion(): bool
    {
        return $this->delete_scheduled_at !== null;
    }

    /**
     * Bookings that are still LIVE, in the client's sense of the word: the client is
     * counting on this listing. Terminal ones (completed/cancelled/rejected) are history
     * and hold nothing up.
     */
    public const LIVE_BOOKING_STATUSES = Booking::LIVE_STATUSES;

    /** Money that has not finished moving. A payout still owed is a reason to keep the row. */
    public const UNSETTLED_PAYMENT_STATUSES = Payment::UNSETTLED_STATUSES;

    /**
     * §12 · UC-37 — why this listing cannot be destroyed yet, in the words a human needs.
     *
     * Owner 2026-08-03: "No deben poder borrarse ads que tengan aún clientes usándolos."
     * The answer is a LIST, not a boolean: a refusal that cannot say what is blocking it
     * leaves the provider with no move except to guess. Empty array = nothing blocks.
     */
    public function deletionBlockers(): array
    {
        $liveBookings = $this->bookings()
            ->whereIn('status', self::LIVE_BOOKING_STATUSES)
            ->count();

        $unsettledPayments = Payment::whereIn('status', self::UNSETTLED_PAYMENT_STATUSES)
            ->whereIn('booking_id', $this->bookings()->select('id'))
            ->count();

        $blockers = [];

        if ($liveBookings > 0) {
            $blockers[] = [
                'kind' => 'live_bookings',
                'count' => $liveBookings,
                'message' => $liveBookings . ' booking(s) are still live on this listing.',
            ];
        }

        if ($unsettledPayments > 0) {
            $blockers[] = [
                'kind' => 'unsettled_payments',
                'count' => $unsettledPayments,
                'message' => $unsettledPayments . ' payment(s) on this listing have not finished settling.',
            ];
        }

        return $blockers;
    }

    /** Admin moderation is in force on this listing (§12 level 2). */
    public function isTakenDown(): bool
    {
        return $this->taken_down_at !== null;
    }

    /**
     * The three levels of §12 collapsed into the one question the catalog asks:
     * may a client see and book this listing right now?
     *
     * 1. the provider paused it        → is_active = false
     * 2. admin took it down            → taken_down_at is set
     * 3. admin froze the account       → the OWNER's frozen_at is set
     * 4. a deletion is programmed      → publication_status = unpublished (UC-37)
     * 5. the OWNER's ACCOUNT is queued for deletion → accounts.publication_status
     *    = unpublished (AD-delguard-09). One column takes the whole catalog of a
     *    departing provider out at once, with no per-listing write to undo later.
     *
     * Kept in one scope so a new listing surface cannot honour four of the five.
     */
    public function scopeBookable(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('publication_status', '!=', self::PUBLICATION_UNPUBLISHED)
            ->whereNull('taken_down_at')
            ->whereHas('user', fn (Builder $q) => $q->whereNull('frozen_at'))
            // whereDoesntHave, not whereHas: a listing whose owner somehow has no
            // account row must not silently vanish from the catalog.
            ->whereDoesntHave(
                'user.account',
                fn (Builder $q) => $q->where('publication_status', Account::PUBLICATION_UNPUBLISHED),
            );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(SpacePhoto::class);
    }

    public function availabilities(): HasMany
    {
        return $this->hasMany(SpaceAvailability::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function ads(): HasMany
    {
        return $this->hasMany(Ad::class);
    }

    // UC-8 · design.json §10 — chats this space is attached to (space is now just one
    // possible chat_object; the old space_id anchor on conversations is retired).
    public function chatObjects(): MorphMany
    {
        return $this->morphMany(ChatObject::class, 'objectable');
    }
}
