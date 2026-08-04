<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Proof extends Model
{
    /**
     * Q42/Q53 — the whole vocabulary of proofs.status, three values and no more.
     * Named for the event, not for a queue: `proof_uploaded` says the provider
     * uploaded, which is the fact; who looks at it next is the client's business.
     */
    public const STATUS_UPLOADED = 'proof_uploaded';

    public const STATUS_CLIENT_ACCEPTED = 'client_accepted';

    public const STATUS_CLIENT_REJECTED = 'client_rejected';

    protected $appends = ['file_url'];

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

    public function getFileUrlAttribute(): string
    {
        return $this->file_path ? url('storage/' . $this->file_path) : '';
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
