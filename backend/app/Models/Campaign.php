<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * design.json §3/§21 — a campaign is scoped to an ACCOUNT.
 *
 * WHY `user_id` SURVIVED `account_id` (the decision the migration asks you to
 * look up here): they are not the same fact.
 *   - `account_id` is the SCOPE — who may see and act on this campaign. It is
 *     what collaborators, billing and §3 are defined against.
 *   - `user_id` is the OWNER-USER who created it. Under MVP 1:1 that is derivable
 *     from the account; under the multi-owner accounts this table exists to make
 *     possible, it is the only record of which human filed it.
 * Dropping user_id would also rewrite the §21 ownership chain, every client
 * controller and OwnershipChainTest for zero behaviour change today. Keeping it
 * costs one auto-filled column and the rule that the two never disagree, which
 * the creating() hook below is what enforces.
 */
class Campaign extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'description',
        'status',
        'start_date',
        'end_date',
        'budget',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'budget' => 'decimal:2',
        ];
    }

    /**
     * account_id is DERIVED from the owner user, never passed in. A caller that
     * could choose it could file a campaign into someone else's account.
     */
    protected static function booted(): void
    {
        static::creating(function (self $campaign): void {
            $campaign->account_id = User::find($campaign->user_id)?->account_id;
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function adsets(): HasMany
    {
        return $this->hasMany(Adset::class);
    }

    /**
     * §3 — collaborators are ACCOUNT-scoped, so a campaign no longer HAS
     * collaborators; it is worked on by the people its account granted. Reading
     * them through the account is what makes "one person = one grant" visible.
     */
    public function collaborators(): HasManyThrough
    {
        return $this->hasManyThrough(
            Collaborator::class,
            Account::class,
            'id',            // accounts.id
            'account_id',    // collaborators.account_id
            'account_id',    // campaigns.account_id
            'id',            // accounts.id
        );
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    // UC-8 · design.json §10 — chats this campaign is attached to (replaces retired tickets).
    public function chatObjects(): MorphMany
    {
        return $this->morphMany(ChatObject::class, 'objectable');
    }
}
