<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Facades\Cache;

/**
 * Key-Value Einstellungen fuer Rechnungsversand pro Tenant.
 */
class InvoiceSetting extends \Illuminate\Database\Eloquent\Model
{
    use HasUuids;
    use \App\Traits\BelongsToTenant; // T-2: automatischer Mandanten-Filter (TenantScope) + tenant_id-Autofill

    protected $fillable = ['tenant_id', 'key', 'value'];

    /**
     * Einstellung lesen (mit Cache).
     */
    public static function get(string $key, mixed $default = null, ?string $tenantId = null): mixed
    {
        try {
            $tenantId = $tenantId ?? auth()->user()?->tenant_id;
        } catch (\Exception $e) {
            $tenantId = session('tenant_id');
        }
        if (! $tenantId) {
            return $default;
        }

        $cacheKey = "invoice_settings:{$tenantId}:{$key}";

        return Cache::remember($cacheKey, 60, function () use ($tenantId, $key, $default) {
            $setting = static::where('tenant_id', $tenantId)
                ->where('key', $key)
                ->first();

            return $setting ? $setting->value : $default;
        });
    }

    /**
     * Einstellung schreiben (Cache invalidieren).
     */
    public static function set(string $key, mixed $value, ?string $tenantId = null): void
    {
        try {
            $tenantId = $tenantId ?? auth()->user()?->tenant_id;
        } catch (\Exception $e) {
            $tenantId = session('tenant_id');
        }
        if (! $tenantId) {
            return;
        }

        static::updateOrCreate(
            ['tenant_id' => $tenantId, 'key' => $key],
            ['value' => (string) $value]
        );

        Cache::forget("invoice_settings:{$tenantId}:{$key}");
    }

    /**
     * Boolean-Einstellung pruefen.
     */
    public static function enabled(string $key, ?string $tenantId = null): bool
    {
        $value = static::get($key, 'false', $tenantId);

        return in_array(strtolower((string) $value), ['true', '1', 'yes', 'on'], true);
    }
}
