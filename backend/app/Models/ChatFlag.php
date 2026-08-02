<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * UC-7/UC-22 · design.json §10/§15 — a volatile human annotation on a chat, with
 * history (supersede-in-place). A flag NEVER grants access; the money STATE stays
 * on payments.status. Support RAISES money flags; Payments EXECUTES via §8 routes.
 */
class ChatFlag extends Model
{
    public const TYPE_PAYMENT_HELD = 'payment_held';
    public const TYPE_REFUND = 'refund';
    public const TYPE_PAYOUT_HOLD = 'payout_hold';
    public const TYPE_CANCEL = 'cancel';
    public const TYPE_MISMATCH = 'mismatch';
    public const TYPE_OTHER = 'other';

    public const TYPES = [
        self::TYPE_PAYMENT_HELD,
        self::TYPE_REFUND,
        self::TYPE_PAYOUT_HOLD,
        self::TYPE_CANCEL,
        self::TYPE_MISMATCH,
        self::TYPE_OTHER,
    ];

    protected $fillable = [
        'chat_id',
        'type',
        'reason',
        'active',
        'created_by_user_id',
        'superseded_at',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'superseded_at' => 'datetime',
        ];
    }

    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
