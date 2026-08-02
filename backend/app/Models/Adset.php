<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Adset extends Model
{
    protected $fillable = [
        'campaign_id',
        'name',
        'latitude',
        'longitude',
        'location_name',
        'radius_km',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'radius_km' => 'decimal:2',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function ads(): HasMany
    {
        return $this->hasMany(Ad::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    // UC-8 · design.json §10 — chats this adset is attached to (replaces retired tickets).
    public function chatObjects(): MorphMany
    {
        return $this->morphMany(ChatObject::class, 'objectable');
    }
}
