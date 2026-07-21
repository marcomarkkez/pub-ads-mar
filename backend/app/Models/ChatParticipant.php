<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * UC-21/UC-27 · design.md §10/§15 — PER-PERSON membership on a chat. Access is by
 * MEMBERSHIP/role (never a flag). Support join = announced=true; Admin silent entry
 * is a forensic (announced=false) row.
 */
class ChatParticipant extends Model
{
    public const SIDE_CLIENT = 'client';
    public const SIDE_PROVIDER = 'provider';
    public const SIDE_SUPPORT = 'support';
    public const SIDE_PAYMENTS = 'payments';
    public const SIDE_ADMIN = 'admin';

    protected $fillable = [
        'chat_id',
        'user_id',
        'side',
        'announced',
        'joined_at',
        'left_at',
    ];

    protected function casts(): array
    {
        return [
            'announced' => 'boolean',
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }

    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
