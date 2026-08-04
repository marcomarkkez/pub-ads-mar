<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * design.json §3 (UC-19/UC-20) — a person granted access to ONE account.
 *
 * ACCOUNT-scoped, never campaign-scoped: "a collaborator points to account_id
 * (never a campaign or space) and, per role, sees/acts across ALL of that
 * account's campaigns/spaces." The DB key `unique(account_id, email)` is the
 * whole of "one person = one grant" — see the migration for the duplicate-invite
 * bug the old `unique(campaign_id, email)` allowed.
 */
class Collaborator extends Model
{
    public const ROLES = ['installator', 'publicist', 'manager'];

    /** §3 — the two CLIENT-side subroles an account owner may invite. */
    public const CLIENT_ROLES = ['publicist', 'manager'];

    public static array $ROLE_CAPS = [
        'installator' => ['proofs.create'],
        'publicist' => ['campaigns.manage', 'ads.manage', 'tickets.create'],
        'manager' => ['*'],
    ];

    protected $fillable = [
        'account_id',
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

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
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
