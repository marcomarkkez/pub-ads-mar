<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class SystemConfiguration extends Model
{
    /**
     * §12 (CF-config-01) — the config surface is a FIXED set, not a key/value scratchpad.
     *
     * Without this, `PUT /admin/configurations` took any string as a key: a typo like
     * `proof_deadline_day` created a brand-new row, returned 200, showed up in the admin
     * screen next to the real one, and left the actual deadline untouched. The admin got
     * every signal of success and changed nothing.
     *
     * A key belongs here once something reads it. `reupload_window_hours` is deliberately
     * absent — §7 abolished the re-upload window (owner 2026-07-10).
     */
    public const KNOWN_KEYS = [
        'proof_deadline_days',
        'strike_window_days',
        'payout_stop_hours',
        'calendar_staleness_days',
        'refund_split_client',
        'refund_split_platform',
        'refund_split_provider',
        'currency',
        // UC-37 — days between programming a deletion and the purge being allowed to run.
        // This key IS read (Provider\SpaceController::destroy); it is not decoration.
        'deletion_grace_days',
    ];

    protected $fillable = [
        'key',
        'value',
        'updated_by_user_id',
    ];

    protected static function booted(): void
    {
        // Keep the per-key cache fresh whenever a row changes.
        static::saved(fn (self $config) => Cache::forget(self::cacheKey($config->key)));
        static::deleted(fn (self $config) => Cache::forget(self::cacheKey($config->key)));
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    protected static function cacheKey(string $key): string
    {
        return "system_config:{$key}";
    }

    /**
     * Fetch a single config value (cached 60 min), falling back to $default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = Cache::remember(
            self::cacheKey($key),
            now()->addMinutes(60),
            fn () => static::query()->where('key', $key)->value('value'),
        );

        return $value ?? $default;
    }

    /**
     * Persist many key/value pairs at once.
     *
     * @param  array<string, mixed>  $pairs
     */
    public static function setMany(array $pairs, ?int $updatedByUserId = null): void
    {
        foreach ($pairs as $key => $value) {
            static::updateOrCreate(
                ['key' => $key],
                [
                    'value' => is_scalar($value) || $value === null ? (string) $value : json_encode($value),
                    'updated_by_user_id' => $updatedByUserId,
                ],
            );
        }
    }
}
