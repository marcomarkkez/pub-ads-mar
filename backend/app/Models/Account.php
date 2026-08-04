<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * design.json §3 (AC-accounts-01/02) — the Account, a first-class object.
 *
 * "Every owner user — a client or a provider — has exactly ONE account; for MVP
 *  an account IS that single owner user (1 user = 1 account)."
 *
 * The 1:1 is a UNIQUE INDEX on users.account_id, so the owner→account resolver is
 * just `$user->account` and the account→owner resolver is `$account->owner`.
 * There is deliberately no service, no registry and no lookup layer around that:
 * under 1:1 there is nothing for one to do, and an abstraction the MVP cannot
 * exercise is an abstraction nobody can verify.
 */
class Account extends Model
{
    public const TYPE_CLIENT = 'client';

    public const TYPE_PROVIDER = 'provider';

    protected $fillable = [
        'name',
        'type',
    ];

    /**
     * The account's owner user. hasOne, not belongsTo: the FK lives on `users`
     * (owner 2026-08-01) precisely so this can become hasMany without a schema
     * change the day an account has several owners.
     */
    public function owner(): HasOne
    {
        return $this->hasOne(User::class);
    }

    /** Every owner user of this account — exactly one for the whole MVP. */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    /** §3 — collaborators hang off the ACCOUNT, never off a campaign or a space. */
    public function collaborators(): HasMany
    {
        return $this->hasMany(Collaborator::class);
    }

    /**
     * §3 — "Every user gets an account at registration." Called from the
     * User::created hook, so registration, the admin's user CRUD, the seeders and
     * every factory in the test suite all get one for free and none of them can
     * forget. Staff roles get NULL: `type` is client|provider and a staff user
     * owns no campaigns or spaces.
     */
    public static function provisionFor(User $user): ?self
    {
        if ($user->account_id !== null || ! in_array($user->role, [self::TYPE_CLIENT, self::TYPE_PROVIDER], true)) {
            return null;
        }

        $account = static::create([
            'name' => $user->company_name ?: $user->name,
            'type' => $user->role,
        ]);

        $user->forceFill(['account_id' => $account->id])->saveQuietly();

        return $account;
    }
}
