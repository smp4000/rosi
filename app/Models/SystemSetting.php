<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Systemweite Key-Value Einstellungen (Admin-Bereich).
 * Nicht tenant-gebunden — fuer Admin-SMTP, globale API-Keys, etc.
 */
class SystemSetting extends Model
{
    use HasUuids;

    protected $fillable = ['group', 'key', 'value', 'is_encrypted'];

    protected $casts = [
        'is_encrypted' => 'boolean',
    ];

    private const SENSITIVE_KEYS = [
        'smtp_password',
        'api_key',
        'perplexity_api_key',
        'telegram_bot_token',
    ];

    // ---------------------------------------------------------------
    //  Lesen
    // ---------------------------------------------------------------

    public static function get(string $key, mixed $default = null, string $group = 'general'): mixed
    {
        $cacheKey = "system_settings:{$group}:{$key}";

        return Cache::remember($cacheKey, 60, function () use ($group, $key, $default) {
            $setting = static::where('group', $group)
                ->where('key', $key)
                ->first();

            if (! $setting) {
                return $default;
            }

            return $setting->is_encrypted
                ? static::decrypt($setting->value, $default)
                : $setting->value;
        });
    }

    public static function getGroup(string $group): array
    {
        $cacheKey = "system_settings:group:{$group}";

        return Cache::remember($cacheKey, 60, function () use ($group) {
            $settings = static::where('group', $group)->get();

            $result = [];
            foreach ($settings as $setting) {
                $result[$setting->key] = $setting->is_encrypted
                    ? static::decrypt($setting->value)
                    : $setting->value;
            }

            return $result;
        });
    }

    // ---------------------------------------------------------------
    //  Schreiben
    // ---------------------------------------------------------------

    public static function set(string $key, mixed $value, string $group = 'general'): void
    {
        $isSensitive = in_array($key, self::SENSITIVE_KEYS, true);
        $storedValue = $isSensitive && $value !== null && $value !== ''
            ? Crypt::encryptString((string) $value)
            : (string) $value;

        static::updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => $storedValue, 'is_encrypted' => $isSensitive && $value !== null && $value !== '']
        );

        Cache::forget("system_settings:{$group}:{$key}");
        Cache::forget("system_settings:group:{$group}");
    }

    public static function setMany(array $values, string $group = 'general'): void
    {
        foreach ($values as $key => $value) {
            static::set($key, $value, $group);
        }
    }

    // ---------------------------------------------------------------
    //  Hilfsfunktionen
    // ---------------------------------------------------------------

    public static function enabled(string $key, string $group = 'general'): bool
    {
        $value = static::get($key, 'false', $group);

        return in_array(strtolower((string) $value), ['true', '1', 'yes', 'on'], true);
    }

    private static function decrypt(?string $value, mixed $default = null): mixed
    {
        if ($value === null || $value === '') {
            return $default;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return $value;
        }
    }
}
