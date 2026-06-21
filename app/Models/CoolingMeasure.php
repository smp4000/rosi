<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Maßnahmenkatalog Temperaturkontrolle (ARAL HQM 7.0).
 * System-Eintraege (tenant_id null) + optional pro Mandant erweiterbar.
 */
class CoolingMeasure extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id', 'number', 'text', 'is_system', 'is_active', 'sort',
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /** Maßnahmen fuer einen Mandanten: System-Katalog + eigene Eintraege. */
    public static function forTenant(?string $tenantId)
    {
        return static::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))
            ->orderBy('number')
            ->get();
    }
}
