<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletEntry extends Model
{
    protected $fillable = [
        'user_id',
        'amount_centavos',
        'type',
        'ref_type',
        'ref_id',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'amount_centavos' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Balance for a user = SUM(amount_centavos).
     */
    public static function balanceFor(int $userId): int
    {
        return (int) static::where('user_id', $userId)->sum('amount_centavos');
    }
}
