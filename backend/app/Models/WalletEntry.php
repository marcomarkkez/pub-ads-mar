<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletEntry extends Model
{
    protected $fillable = [
        'user_id',
        'amount',
        'type',
        'ref_type',
        'ref_id',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Balance for a user = SUM(amount) in decimal MXN pesos.
     */
    public static function balanceFor(int $userId): float
    {
        return (float) static::where('user_id', $userId)->sum('amount');
    }
}
