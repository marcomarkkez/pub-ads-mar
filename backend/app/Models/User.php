<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
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

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
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

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function spaces(): HasMany
    {
        return $this->hasMany(Space::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'client_user_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assigned_to_user_id');
    }

    public function clientConversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'client_user_id');
    }

    public function providerConversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'provider_user_id');
    }

    public function walletEntries(): HasMany
    {
        return $this->hasMany(WalletEntry::class);
    }
}
