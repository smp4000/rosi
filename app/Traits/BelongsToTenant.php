<?php

namespace App\Traits;

use App\Models\Tenant;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trait fuer mandantenfaehige Models.
 * Fuegt automatisch den TenantScope hinzu und setzt die tenant_id beim Erstellen.
 *
 * Verwendung: `use BelongsToTenant;` im Model hinzufuegen.
 * Voraussetzung: Das Model hat eine `tenant_id` Spalte.
 */
trait BelongsToTenant
{
    /**
     * Boot-Methode: TenantScope registrieren und tenant_id automatisch setzen.
     */
    public static function bootBelongsToTenant(): void
    {
        // Globalen Scope hinzufuegen - filtert automatisch nach tenant_id
        static::addGlobalScope(new TenantScope());

        // Beim Erstellen automatisch die tenant_id aus der Session setzen
        static::creating(function ($model) {
            if (empty($model->tenant_id) && session()->has('tenant_id')) {
                $model->tenant_id = session('tenant_id');
            }
        });
    }

    /**
     * Beziehung zum Mandanten.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Query ohne Tenant-Filter ausfuehren (z.B. fuer Super-Admin).
     * Beispiel: GasStation::withoutTenantScope()->get()
     */
    public function scopeWithoutTenantScope($query)
    {
        return $query->withoutGlobalScope(TenantScope::class);
    }
}
