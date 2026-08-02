<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * UC-8/UC-9 · design.json §10 — a message on a chat. kind=system records origin and
 * every transformation (flag change, support join, object attach/detach, resolve/close).
 */
class Message extends Model
{
    protected $fillable = [
        'chat_id',
        'sender_user_id',
        'body',
        'kind',
        'is_read',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
        ];
    }

    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }
}
