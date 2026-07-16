<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    // Thread types (design.md §7/§10/§11).
    public const TYPE_DIRECT = 'direct';              // client ↔ provider
    public const TYPE_INTERNAL = 'internal';          // Support ↔ Payments (staff only)
    public const TYPE_SUPPORT_CLIENT = 'support_client';     // Support ↔ Client
    public const TYPE_SUPPORT_PROVIDER = 'support_provider'; // Support ↔ Provider

    protected $fillable = [
        'space_id',
        'client_user_id',
        'provider_user_id',
        'type',
        'support_joined_at',
    ];

    protected function casts(): array
    {
        return [
            'support_joined_at' => 'datetime',
        ];
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
