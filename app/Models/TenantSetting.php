<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Zentrale Key-Value Einstellungen pro Tenant.
 * Unterstuetzt Gruppierung (mail, invoice, api, etc.) und Verschluesselung.
 */
class TenantSetting extends Model
{
    use HasUuids;
    use \App\Traits\BelongsToTenant; // T-2: automatischer Mandanten-Filter (TenantScope) + tenant_id-Autofill

    protected $fillable = ['tenant_id', 'group', 'key', 'value', 'is_encrypted'];

    protected $casts = [
        'is_encrypted' => 'boolean',
    ];

    /**
     * Keys die automatisch verschluesselt werden.
     */
    private const SENSITIVE_KEYS = [
        'smtp_password',
        'api_key',
        'perplexity_api_key',
        'telegram_bot_token',
    ];

    // ---------------------------------------------------------------
    //  Lesen
    // ---------------------------------------------------------------

    /**
     * Einstellung lesen (mit Cache).
     */
    public static function get(string $key, mixed $default = null, ?string $tenantId = null, string $group = 'general'): mixed
    {
        $tenantId = static::resolveTenantId($tenantId);
        if (! $tenantId) {
            return $default;
        }

        $cacheKey = "tenant_settings:{$tenantId}:{$group}:{$key}";

        return Cache::remember($cacheKey, 60, function () use ($tenantId, $group, $key, $default) {
            $setting = static::where('tenant_id', $tenantId)
                ->where('group', $group)
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

    /**
     * Alle Einstellungen einer Gruppe lesen.
     */
    public static function getGroup(string $group, ?string $tenantId = null): array
    {
        $tenantId = static::resolveTenantId($tenantId);
        if (! $tenantId) {
            return [];
        }

        $cacheKey = "tenant_settings:{$tenantId}:group:{$group}";

        return Cache::remember($cacheKey, 60, function () use ($tenantId, $group) {
            $settings = static::where('tenant_id', $tenantId)
                ->where('group', $group)
                ->get();

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

    /**
     * Einstellung schreiben (Cache invalidieren).
     */
    public static function set(string $key, mixed $value, ?string $tenantId = null, string $group = 'general'): void
    {
        $tenantId = static::resolveTenantId($tenantId);
        if (! $tenantId) {
            return;
        }

        $isSensitive = in_array($key, self::SENSITIVE_KEYS, true);
        $storedValue = $isSensitive && $value !== null && $value !== ''
            ? Crypt::encryptString((string) $value)
            : (string) $value;

        static::updateOrCreate(
            ['tenant_id' => $tenantId, 'group' => $group, 'key' => $key],
            ['value' => $storedValue, 'is_encrypted' => $isSensitive && $value !== null && $value !== '']
        );

        // Einzel-Key und Gruppen-Cache invalidieren
        Cache::forget("tenant_settings:{$tenantId}:{$group}:{$key}");
        Cache::forget("tenant_settings:{$tenantId}:group:{$group}");
    }

    /**
     * Mehrere Einstellungen einer Gruppe auf einmal setzen.
     */
    public static function setMany(array $values, string $group = 'general', ?string $tenantId = null): void
    {
        foreach ($values as $key => $value) {
            static::set($key, $value, $tenantId, $group);
        }
    }

    // ---------------------------------------------------------------
    //  Hilfsfunktionen
    // ---------------------------------------------------------------

    /**
     * Boolean-Einstellung pruefen.
     */
    public static function enabled(string $key, ?string $tenantId = null, string $group = 'general'): bool
    {
        $value = static::get($key, 'false', $tenantId, $group);

        return in_array(strtolower((string) $value), ['true', '1', 'yes', 'on'], true);
    }

    /**
     * Tenant-ID aus Auth oder Session ermitteln.
     */
    private static function resolveTenantId(?string $tenantId): ?string
    {
        if ($tenantId) {
            return $tenantId;
        }

        try {
            $tenantId = auth()->user()?->tenant_id;
        } catch (\Exception $e) {
            $tenantId = session('tenant_id');
        }

        return $tenantId;
    }

    /**
     * Wert sicher entschluesseln.
     */
    private static function decrypt(?string $value, mixed $default = null): mixed
    {
        if ($value === null || $value === '') {
            return $default;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            // Fallback: Wert ist moeglicherweise nicht verschluesselt (Migration)
            return $value;
        }
    }

    // ---------------------------------------------------------------
    //  Relationship
    // ---------------------------------------------------------------

    public function tenant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
