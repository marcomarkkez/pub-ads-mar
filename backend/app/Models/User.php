<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'company_name',
        'address',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * design.json §3 — "Every user gets an account at registration."
     *
     * The hook, not each caller: registration, the admin user CRUD, the seeders
     * and every User::factory() in the suite create users, and an account that
     * only appears when someone remembers to ask for it is an invariant that is
     * false somewhere. `account_id` is NOT fillable — nobody assigns themselves
     * an account through a request payload.
     *
     * The second line is the mirror of the first, and belongs here for the same
     * reason: a person is normally INVITED to collaborate before they have a user
     * row, so `collaborators.user_id` is NULL and the invitation is keyed only by
     * email. The moment that email becomes a user, the two halves must be joined —
     * otherwise every `user_id`-keyed lookup in the app (chat ACLs, `collaborating_on`)
     * skips a real collaborator and nothing anywhere reports it. The invitation stays
     * `pending`: signing up is not the same act as agreeing to run someone's account.
     */
    protected static function booted(): void
    {
        static::created(function (self $user): void {
            Account::provisionFor($user);
            Collaborator::linkNewUser($user);
        });
    }

    /**
     * UC-29 · §12 — `frozen_at`/`freeze_reason` are NOT fillable. `is_active` is the
     * admin CRM's deactivate switch and Support may write it too; a freeze is a
     * different, account-level moderation state that only
     * Admin\ModerationController sets, so it must not be reachable through any
     * validated update payload.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'frozen_at' => 'datetime',
        ];
    }

    /** §12 level 3 — the account is frozen by admin moderation. */
    public function isFrozen(): bool
    {
        return $this->frozen_at !== null;
    }

    public function isClient(): bool { return $this->role === 'client'; }
    public function isProvider(): bool { return $this->role === 'provider'; }
    public function isAdmin(): bool { return $this->role === 'admin'; }
    public function isSupport(): bool { return $this->role === 'support'; }
    public function isPayments(): bool { return $this->role === 'payments'; }
    public function isStaff(): bool { return in_array($this->role, ['admin', 'support', 'payments']); }

    public function hasPermission(string $resource, string $action): bool
    {
        return RolePermission::roleHasPermission($this->role, $resource, $action);
    }

    public function getPermissions(): array
    {
        return RolePermission::getCachedPermissions($this->role);
    }

    /**
     * §3 (AC-accounts-02) — the owner→account resolver. Under the MVP 1:1 this
     * IS the resolver; there is nothing else to resolve.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * §3 (owner 2026-08-04) — "un dueño es la persona que abre la cuenta y puede añadir
     * colaboradores, así de simple." This is where that sentence is computed, once.
     *
     * It lived inline in AuthController::me() only, so `/login` and `/register` answered
     * without it and a user who had just signed in had no way to know whether the
     * Collaborators tab was theirs — the menu only appeared after a full page reload put
     * them through `/me`. Copying the block into the other two responses would have been
     * the faster fix and the worse one: the day "owner" stops meaning "has an account"
     * (multi-owner accounts, staff-managed accounts), two of the three copies keep
     * answering the old question and nobody notices, because each one looks right alone.
     *
     * `is_owner`: the link is `users.account_id` (not `accounts.owner_user_id` — owner
     * ruling 2026-08-01) and it carries a UNIQUE index, which IS the MVP "1 user = 1
     * account" rule. So the single user pointing at an account is its owner, and the test
     * reduces to "I have an account". Staff carry `account_id = NULL` and are never
     * owners. When that unique index is dropped this line becomes a real comparison — in
     * one place, which is the whole point of the method existing.
     *
     * `collaborating_on`: the other half, and the half the UI actually needed — the
     * accounts where I am someone ELSE's collaborator. Owning my account and helping run
     * yours are two different facts, and a screen that cannot tell them apart shows the
     * wrong menu. Only `accepted` grants count: a pending invitation grants nothing yet
     * and a revoked one granted something once.
     *
     * @return array{account_id: int|null, is_owner: bool, collaborating_on: \Illuminate\Support\Collection}
     */
    public function accountContext(): array
    {
        return [
            'account_id' => $this->account_id,
            'is_owner' => $this->account_id !== null,
            'collaborating_on' => Collaborator::where('user_id', $this->id)
                ->where('status', Collaborator::STATUS_ACCEPTED)
                ->pluck('account_id'),
        ];
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    /** §3 — collaborator grants this user holds INTO other people's accounts. */
    public function collaborations(): HasMany
    {
        return $this->hasMany(Collaborator::class);
    }

    public function spaces(): HasMany
    {
        return $this->hasMany(Space::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'client_user_id');
    }

    // UC-8/UC-9 · design.json §10 — chats anchored on this user's client / provider side.
    public function clientChats(): HasMany
    {
        return $this->hasMany(Chat::class, 'client_user_id');
    }

    public function providerChats(): HasMany
    {
        return $this->hasMany(Chat::class, 'provider_user_id');
    }

    public function chatParticipations(): HasMany
    {
        return $this->hasMany(ChatParticipant::class);
    }

    public function walletEntries(): HasMany
    {
        return $this->hasMany(WalletEntry::class);
    }
}
