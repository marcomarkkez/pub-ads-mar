<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * UC-8 · design.md §10/§15 — a polymorphic object attached to a chat
 * (Ad|Adset|Campaign|Space|Payment|Booking). Attaching is OWNERSHIP-CHECKED in the
 * controller (R2/§16): a chat never exposes an object its attacher couldn't see.
 */
class ChatObject extends Model
{
    protected $fillable = [
        'chat_id',
        'objectable_type',
        'objectable_id',
        'attached_by_user_id',
    ];

    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    public function objectable(): MorphTo
    {
        return $this->morphTo();
    }

    public function attachedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attached_by_user_id');
    }
}
