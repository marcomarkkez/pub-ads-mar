<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Ticket extends Model
{
    protected $fillable = [
        'user_id',
        'ticketable_type',
        'ticketable_id',
        'subject',
        'description',
        'status',
        'priority',
        'assigned_to_user_id',
    ];

    protected $appends = ['reference_type', 'reference_id'];

    public function getReferenceTypeAttribute(): ?string
    {
        return $this->ticketable_type;
    }

    public function getReferenceIdAttribute(): ?int
    {
        return $this->ticketable_id;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ticketable(): MorphTo
    {
        return $this->morphTo();
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class);
    }
}
