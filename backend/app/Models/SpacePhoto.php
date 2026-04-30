<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class SpacePhoto extends Model
{
    protected $fillable = [
        'space_id',
        'file_path',
        'file_name',
        'is_primary',
        'sort_order',
    ];

    protected $appends = ['file_url'];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function getFileUrlAttribute(): string
    {
        return url('storage/' . $this->file_path);
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }
}
