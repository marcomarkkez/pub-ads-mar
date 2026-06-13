<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class SystemConfiguration extends Model
{
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
