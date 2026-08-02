<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * UC-8/UC-9/UC-21/UC-28 · design.json §10/§15/§16 — the ONE communication primitive.
 * A "ticket" = a chat with a Support participant. Nature is DERIVED (never a column)
 * from participants+objects+flags. Access is by MEMBERSHIP/role, NEVER by a flag.
 */
class Chat extends Model
{
    // States (§10). UI collapses these to Abierto (open/in_progress/resolved) / Cerrado (closed).
    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED = 'closed';

    // Derived natures (§10/§16) — for UI labels, oversight filters and ACL branching only.
    public const NATURE_CLIENT_PROVIDER = 'client_provider'; // both anchors → inquiry (mask PII)
    public const NATURE_SUPPORT_CLIENT = 'support_client';   // client only → dispute/contact-support
    public const NATURE_SUPPORT_PROVIDER = 'support_provider'; // provider only → dispute
    public const NATURE_INTERNAL = 'internal';               // neither anchor → Support↔Payments

    // Chat-capable collaborator subroles (§10). Installators are DENIED (proof-upload only).
    public const CLIENT_CHAT_SUBROLES = ['publicist', 'manager'];
    public const PROVIDER_CHAT_SUBROLES = ['sales', 'supervisor']; // provider collabs = AFTER-MVP schema

    protected $fillable = [
        'opened_by_user_id',
        'client_user_id',
        'provider_user_id',
        'status',
        'support_joined_at',
        'resolved_at',
        'closed_at',
        'closed_by_user_id',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'support_joined_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'last_message_at' => 'datetime',
        ];
    }

    // ── Relations ────────────────────────────────────────────────────────────

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_user_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_user_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function objects(): HasMany
    {
        return $this->hasMany(ChatObject::class);
    }

    public function flags(): HasMany
    {
        return $this->hasMany(ChatFlag::class);
    }

    public function activeFlags(): HasMany
    {
        return $this->hasMany(ChatFlag::class)->where('active', true);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ChatParticipant::class);
    }

    // ── Derived nature (§10/§16) — NEVER persisted ──────────────────────────

    /**
     * UC-28 · design.json §10/§16 — derive the chat's nature from its side anchors.
     * Support/Payments presence is role-derived at request time; the anchors alone
     * distinguish the four kinds, which is exactly what the ACL + PII policy need.
     */
    public function deriveNature(): string
    {
        $hasClient = $this->client_user_id !== null;
        $hasProvider = $this->provider_user_id !== null;

        if ($hasClient && $hasProvider) {
            return self::NATURE_CLIENT_PROVIDER;
        }
        if ($hasClient) {
            return self::NATURE_SUPPORT_CLIENT;
        }
        if ($hasProvider) {
            return self::NATURE_SUPPORT_PROVIDER;
        }

        return self::NATURE_INTERNAL;
    }

    /** A staff-run chat (no masked client↔provider pairing) — PII is never masked here. */
    public function isStaffOnly(): bool
    {
        return $this->deriveNature() !== self::NATURE_CLIENT_PROVIDER;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    // ── ACL core (§10/§16; chat-acl-security-review R1-R9) ───────────────────

    /**
     * UC-8/UC-9/UC-21/UC-27 · design.json §10/§16 — read/post gate. TRUE iff the user
     * holds a live participant row OR belongs to a participating ACCOUNT with a
     * chat-capable subrole. A flag NEVER grants access (R1/R2/R7/R8 fixes baked in):
     *  - CLIENT   → the client side (owner or any client collaborator; installator DENIED).
     *  - PROVIDER → the provider side (owner or sales/supervisor; installator DENIED).
     *  - SUPPORT  → any chat whose derived nature is staff-facing (its DERIVED queue),
     *               or a client↔provider chat it has JOINED (participant row).
     *  - PAYMENTS → only money chats it participates in (the internal Support↔Payments chat).
     *  - ADMIN    → NEVER here — Admin is read-only/incognito via /admin/oversight/chats (R1).
     */
    public function userCanAccess(User $user): bool
    {
        // Explicit live participant membership always grants access (support join,
        // payments added, etc.). Admin is excluded below regardless.
        if ($user->role !== 'admin' && $this->hasLiveParticipant($user)) {
            return true;
        }

        return match ($user->role) {
            'client' => $this->userOnClientSide($user),
            'provider' => $this->userOnProviderSide($user),
            'support' => $this->isStaffOnly(),
            'payments' => $this->deriveNature() === self::NATURE_INTERNAL,
            default => false, // admin + unknown roles: no shared-path access
        };
    }

    public function hasLiveParticipant(User $user): bool
    {
        return $this->participants()
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->exists();
    }

    private function userOnClientSide(User $user): bool
    {
        if ($this->client_user_id === null) {
            return false;
        }
        if ($this->client_user_id === $user->id) {
            return true;
        }

        // A client collaborator (publicist/manager) reaches the account's chats.
        // Installator is excluded — proof-upload only (§10). Collaborators are
        // campaign-scoped in the current schema; the campaign owner IS the account.
        return Collaborator::query()
            ->where('user_id', $user->id)
            ->whereIn('role', self::CLIENT_CHAT_SUBROLES)
            ->whereHas('campaign', fn (Builder $q) => $q->where('user_id', $this->client_user_id))
            ->exists();
    }

    private function userOnProviderSide(User $user): bool
    {
        if ($this->provider_user_id === null) {
            return false;
        }
        if ($this->provider_user_id === $user->id) {
            return true;
        }

        // Provider-side sales/supervisor collaborators are AFTER-MVP (no provider
        // collaborator table yet); the subrole allowlist is enforced here so an
        // installator can never slip in once that table lands.
        return Collaborator::query()
            ->where('user_id', $user->id)
            ->whereIn('role', self::PROVIDER_CHAT_SUBROLES)
            ->exists();
    }

    // ── Visibility scope for the list (index) ────────────────────────────────

    /**
     * UC-8/UC-9/UC-27 · design.json §10 — the chats a user may see in their Messages
     * list. Mirrors userCanAccess() as a query. Admin lists via oversight, not here.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            'client' => $query->where(function (Builder $q) use ($user) {
                $q->where('client_user_id', $user->id)
                    ->orWhereIn('client_user_id', $this->clientAccountsFor($user))
                    ->orWhereIn('id', $this->liveParticipantChatIds($user));
            }),
            'provider' => $query->where(function (Builder $q) use ($user) {
                $q->where('provider_user_id', $user->id)
                    ->orWhereIn('id', $this->liveParticipantChatIds($user));
            }),
            // Support's queue is DERIVED: any staff-facing chat (nature != client↔provider),
            // plus client↔provider chats it has joined.
            'support' => $query->where(function (Builder $q) use ($user) {
                $q->whereNull('client_user_id')
                    ->orWhereNull('provider_user_id')
                    ->orWhereIn('id', $this->liveParticipantChatIds($user));
            }),
            // Payments: money chats it participates in — the internal ones (both anchors null).
            'payments' => $query->where(function (Builder $q) use ($user) {
                $q->where(function (Builder $qq) {
                    $qq->whereNull('client_user_id')->whereNull('provider_user_id');
                })->orWhereIn('id', $this->liveParticipantChatIds($user));
            }),
            default => $query->whereRaw('1 = 0'), // admin/unknown: nothing via the shared list
        };
    }

    /** Client accounts this user collaborates on with a chat-capable subrole. */
    private function clientAccountsFor(User $user): array
    {
        return Collaborator::query()
            ->where('user_id', $user->id)
            ->whereIn('role', self::CLIENT_CHAT_SUBROLES)
            ->with('campaign:id,user_id')
            ->get()
            ->pluck('campaign.user_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function liveParticipantChatIds(User $user): array
    {
        return ChatParticipant::query()
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->pluck('chat_id')
            ->all();
    }
}
