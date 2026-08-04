<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Booking extends Model
{
    /**
     * UC-29 · §12 — the bookings an account FREEZE auto-cancels and refunds:
     * agreed or awaiting agreement, and not started yet.
     *
     * `active` and `waiting_proof` are deliberately absent. A display that is
     * already running is not "upcoming": cancelling it would refund a client for
     * work the provider has performed, and would delete the record a proof and a
     * payout are argued from. Those bookings keep their money state and are
     * reported back to the admin instead.
     */
    public const UPCOMING_STATUSES = ['pending', 'waiting_approval', 'confirmed'];

    /**
     * §12 · UC-37 — bookings that are still LIVE in the client's sense of the word:
     * somebody is counting on them. Terminal ones (completed/cancelled/rejected) are
     * history and hold nothing up.
     *
     * This lives on Booking and not on whoever asks first: Space::deletionBlockers()
     * and Account::deletionBlockers() must mean the same thing by "live", and two
     * copies of a status list are two lists that drift.
     */
    public const LIVE_STATUSES = ['pending', 'waiting_approval', 'confirmed', 'active', 'waiting_proof'];

    protected $fillable = [
        'client_user_id',
        'space_id',
        'ad_id',
        'adset_id',
        'start_date',
        'end_date',
        'total_price',
        'status',
        'rejection_reason',
        'config_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'total_price' => 'decimal:2',
            'config_snapshot' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class);
    }

    public function adset(): BelongsTo
    {
        return $this->belongsTo(Adset::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function proofs(): HasMany
    {
        return $this->hasMany(Proof::class);
    }

    // UC-7 · design.json §10 — chats this booking is attached to (dispute chats).
    public function chatObjects(): MorphMany
    {
        return $this->morphMany(ChatObject::class, 'objectable');
    }
}
