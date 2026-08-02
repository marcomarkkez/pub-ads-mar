<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * design.json §12 (UC-31) — immutable, append-only audit log.
 *
 * Every staff action that carries ✏ or $ in the §2 matrix writes one row here,
 * in the SAME transaction as the state change it records. Admin reads it
 * (`GET /admin/audit`); nobody edits or deletes it.
 *
 * Immutability is enforced twice on purpose: here at the model (loud, works on
 * any driver) and — AFTER-MVP, per §12 — at the database with an INSERT-only
 * grant plus a trigger. The model guard is not security against raw SQL; it is
 * the guarantee that no code path in this app can quietly rewrite history.
 */
class AuditLog extends Model
{
    /** Append-only: rows are never touched again, so there is no updated_at. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'actor_id',
        'actor_role',
        'actor_label',
        'action',
        'target_type',
        'target_id',
        'before',
        'after',
        'context',
    ];

    /**
     * Never mirrored into before/after, whatever the caller passes.
     */
    private const REDACTED = ['password', 'remember_token', 'api_token'];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new RuntimeException('The audit log is append-only; an entry can never be modified.');
        });

        static::deleting(function (): void {
            throw new RuntimeException('The audit log is append-only; an entry can never be deleted.');
        });
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * The API resource slug a model is addressed by, so `?target_type=spaces`
     * matches the `/support/spaces/{id}` route the edit came through.
     */
    public static function slugFor(Model $model): string
    {
        return Str::snake(Str::pluralStudly(class_basename($model)));
    }

    /**
     * Record an action. Pass $before/$after explicitly, or let recordChange()
     * derive them from a dirty model.
     */
    public static function record(
        ?User $actor,
        string $action,
        Model $target,
        ?array $before = null,
        ?array $after = null,
        ?string $context = null,
    ): self {
        return static::create([
            'actor_id' => $actor?->id,
            'actor_role' => $actor?->role ?? 'system',
            'actor_label' => $actor?->name,
            'action' => $action,
            'target_type' => static::slugFor($target),
            'target_id' => $target->getKey(),
            'before' => $before === null ? null : static::scrub($before),
            'after' => $after === null ? null : static::scrub($after),
            'context' => $context,
        ]);
    }

    /**
     * Record an UPDATE from a model that is still dirty, mirroring ONLY the
     * fields that actually changed. Returns null when nothing changed — a
     * no-op PUT should not manufacture an audit entry.
     *
     * Call this BEFORE save(): once saved, the model is clean and has nothing
     * left to compare.
     */
    public static function recordChange(?User $actor, Model $target, ?string $context = null): ?self
    {
        $after = $target->getDirty();

        if ($after === []) {
            return null;
        }

        $before = array_intersect_key($target->getOriginal(), $after);

        // A field the model has never held reads as absent, not as a missing key.
        foreach (array_keys($after) as $key) {
            $before[$key] ??= null;
        }

        return static::record($actor, 'update', $target, $before, $after, $context);
    }

    private static function scrub(array $attributes): array
    {
        foreach (static::REDACTED as $key) {
            if (array_key_exists($key, $attributes)) {
                $attributes[$key] = '[redacted]';
            }
        }

        return $attributes;
    }
}
