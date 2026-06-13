<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Collaborator extends Model
{
    public const ROLES = ['installator', 'publicist', 'manager'];

    public static array $ROLE_CAPS = [
        'installator' => ['proofs.create'],
        'publicist' => ['campaigns.manage', 'ads.manage', 'tickets.create'],
        'manager' => ['*'],
    ];

    protected $fillable = [
        'campaign_id',
        'invited_by_user_id',
        'user_id',
        'email',
        'role',
        'status',
    ];

    public function can(string $cap): bool
    {
        $caps = static::$ROLE_CAPS[$this->role] ?? [];

        return in_array('*', $caps, true) || in_array($cap, $caps, true);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
