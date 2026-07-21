<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Ad extends Model
{
    protected $fillable = [
        'adset_id',
        'space_id',
        'provider_user_id',
        'name',
        'media_type',
        'file_path',
        'file_name',
        'price',
        'pricing_unit',
        'start_date',
        'end_date',
        'status',
        'proof_deadline',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'proof_deadline' => 'datetime',
        ];
    }

    public function adset(): BelongsTo
    {
        return $this->belongsTo(Adset::class);
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_user_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function proofs(): HasMany
    {
        return $this->hasMany(Proof::class);
    }

    // UC-8 · design.md §10 — chats this ad is attached to (replaces retired tickets).
    public function chatObjects(): MorphMany
    {
        return $this->morphMany(ChatObject::class, 'objectable');
    }
}
