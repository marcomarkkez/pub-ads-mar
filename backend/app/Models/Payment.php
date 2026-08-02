<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Payment extends Model
{
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
        ];
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
