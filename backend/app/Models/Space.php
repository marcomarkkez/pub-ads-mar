<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Space extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'type',
        'latitude',
        'longitude',
        'price_per_day',
        'price_per_month',
        'pricing_unit',
        'description',
        'location_text',
        'width',
        'height',
        'ical_url',
        'calendar_keyword',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'price_per_day' => 'decimal:2',
            'price_per_month' => 'decimal:2',
            'width' => 'decimal:2',
            'height' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(SpacePhoto::class);
    }

    public function availabilities(): HasMany
    {
        return $this->hasMany(SpaceAvailability::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function ads(): HasMany
    {
        return $this->hasMany(Ad::class);
    }

    // UC-8 · design.json §10 — chats this space is attached to (space is now just one
    // possible chat_object; the old space_id anchor on conversations is retired).
    public function chatObjects(): MorphMany
    {
        return $this->morphMany(ChatObject::class, 'objectable');
    }
}
