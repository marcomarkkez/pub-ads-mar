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
     */
    protected static function booted(): void
    {
        static::created(function (self $user): void {
            Account::provisionFor($user);
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
