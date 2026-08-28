<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
    /**
     * Every subrole that may appear in the column, both ecosystems at once. Only Support's
     * edit-any route validates against this: it fixes a grant on SOMEBODY's account without
     * being told whose, so it is the one caller that cannot narrow the list by account type.
     * Everything that creates a grant narrows it — see rolesFor().
     */
    public const ROLES = ['installator', 'publicist', 'manager', 'sales', 'supervisor'];

    /** §3 — the two CLIENT-side subroles an account owner may invite. */
    public const CLIENT_ROLES = ['publicist', 'manager'];

    /**
     * §3 — the PROVIDER-side subroles. A separate list, not a superset: the two sides are
     * two ecosystems and share no subrole name, which is why the accepted set has to be
     * chosen by the ACCOUNT's type rather than by whoever is holding the form.
     */
    public const PROVIDER_ROLES = ['installator', 'sales', 'supervisor'];

    /**
     * The subroles an account of this type may hire (owner 2026-08-23 — both sides are
     * companies and both hire, but they do not hire the same jobs).
     *
     * `accounts.type` is client|provider and nothing else, so an unknown type would be a
     * corrupt row; it hires nobody rather than defaulting to one side's list, because a
     * default here means silently filing a person under the wrong ecosystem.
     */
    public static function rolesFor(string $accountType): array
    {
        return match ($accountType) {
            Account::TYPE_CLIENT => self::CLIENT_ROLES,
            Account::TYPE_PROVIDER => self::PROVIDER_ROLES,
            default => [],
        };
    }

    // ── The grant's lifecycle (UC-19/UC-20) ──────────────────────────────────
    //
    // Only `accepted` grants anything. The other three are all "no access", and they
    // are kept apart because WHO closed the door decides what may happen next:
    // the owner may re-invite a `declined`/`revoked` person, and BR-15 lets nobody
    // walk out of an `accepted` one.

    /** Invited, not answered yet. Grants nothing; the person may accept or decline. */
    public const STATUS_PENDING = 'pending';

    /** The person walked in. The ONLY status that grants access — and a one-way door. */
    public const STATUS_ACCEPTED = 'accepted';

    /** The account owner took the access away. */
    public const STATUS_REVOKED = 'revoked';

    /** The invited person never walked in. NOT the same fact as `revoked`. */
    public const STATUS_DECLINED = 'declined';

    /** A grant the owner may re-issue: nobody is inside, and nobody is being let out. */
    public const REINVITABLE_STATUSES = [self::STATUS_REVOKED, self::STATUS_DECLINED];

    public static array $ROLE_CAPS = [
        'installator' => ['proofs.create'],
        'publicist' => ['campaigns.manage', 'ads.manage', 'tickets.create'],
        'manager' => ['*'],
        // Provider-side chat access is decided by Chat::PROVIDER_CHAT_SUBROLES, not by
        // can(); these two hold no capability here YET, and an absent key already means
        // "grants nothing" (see can()). Listing them empty says that on purpose, so the
        // next person does not read the gap as an oversight and hand them '*'.
        'sales' => [],
        'supervisor' => [],
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

    /**
     * §3 (UC-20) — the invitations addressed to ONE person, and the only scope the
     * `/collaborations` endpoints may ever address.
     *
     * This is deliberately NOT the account scope CollaboratorController uses. There the
     * caller acts on their OWN account and the scope is `account_id = mine`; here the
     * caller answers an invitation into somebody ELSE's account, so that scope would
     * match nothing and the endpoints would 404 forever. Copying it "for consistency" is
     * how EH-5 (a permission check with no scope of its own) gets reintroduced — the
     * correct bound here is "this invitation names me", and nothing wider.
     *
     * Two arms because a row can name a person who has no user row yet: `user_id` is NULL
     * on an invitation sent to an email that had not registered, and until that person
     * signs up the email is the only link between the row and the human. Compared
     * case-insensitively — an invite is typed by hand, and "Ana@x.test" is the same
     * person as "ana@x.test". `lower()` is Postgres, which is the only engine this app
     * runs on (see the enum/CHECK migrations, which are already engine-specific).
     */
    public function scopeAddressedTo(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('user_id', $user->id)
            ->orWhereRaw('lower(email) = ?', [mb_strtolower($user->email)]));
    }

    /**
     * §3 — attach a brand-new user to the invitations that were waiting for their email.
     *
     * Called from the `User::created` hook, so it runs for registration, the admin user
     * CRUD and the seeders alike; the invitation is normally sent BEFORE the person has
     * an account, so without this back-link `collaborators.user_id` stays NULL for the
     * rest of the row's life and every `user_id`-keyed lookup in the app (chat ACLs,
     * `collaborating_on`) silently skips a real collaborator.
     *
     * The status is left ALONE, on purpose. Accepting is a deliberate act — it is the
     * moment a person agrees to operate someone else's account, and BR-15 makes it
     * irreversible without Support. Signing up is not that act, and turning it into one
     * would mean a stranger who invited your email decides what you joined.
     *
     * Only pending rows are touched: a revoked or declined grant is a closed episode, and
     * a new signup is not the event that reopens it.
     */
    public static function linkNewUser(User $user): int
    {
        // Staff never collaborate — `accounts.type` is client|provider and every subrole
        // in $ROLE_CAPS is an account-side job. Linking a support agent to an invitation
        // would hand them a second, unaudited way into an account.
        if (! in_array($user->role, ['client', 'provider'], true)) {
            return 0;
        }

        return static::query()
            ->whereNull('user_id')
            ->where('status', self::STATUS_PENDING)
            ->whereRaw('lower(email) = ?', [mb_strtolower($user->email)])
            ->update(['user_id' => $user->id]);
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
