<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Booking extends Model
{
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
