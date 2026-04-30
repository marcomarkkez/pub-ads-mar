<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Proof extends Model
{
    protected $fillable = [
        'ad_id',
        'booking_id',
        'uploaded_by_user_id',
        'media_type',
        'file_path',
        'file_name',
        'notes',
        'status',
        'reviewed_by_user_id',
        'reviewed_at',
        'deadline',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'deadline' => 'datetime',
        ];
    }

    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
