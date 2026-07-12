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
 *
 * ────────────────────────────────────────────────────────────────────────
 * BEWUSST OHNE dieses Trait (T-2, Sicherheits-Audit 07/2026) — bitte NICHT
 * "reparieren", das ist Absicht:
 *
 *  System-Kataloge mit Misch-Daten (tenant_id NULL = System-Eintrag fuer
 *  alle Mandanten + eigene Eintraege je Mandant). Der strikte TenantScope
 *  wuerde die System-Zeilen ausblenden; diese Models filtern selbst per
 *  forTenant()/findForTenant() mit orWhereNull:
 *    - LabelTemplate        (System-Druckvorlagen)
 *    - ArticleGroup         (273 System-Artikelgruppen)
 *    - DepreciationReason   (Standard-Abschreibungsgruende)
 *    - HealthInsurance      (30 System-Krankenkassen)
 *    - CoolingMeasure       (ARAL-Massnahmenkatalog)
 *    - ShiftReturnReason    (Standard-Gruende)
 *    - DocumentTemplate     (System-Vorlagen moeglich)
 *
 *  Echt globale Models (gehoeren keinem Mandanten):
 *    - Brand                (globale Marken-Tabelle)
 *    - AppVersion           (App-/Agent-Versionen fuer alle)
 *    - Tenant               (ist selbst der Mandant)
 *    - User                 (tenant_id nullable: Super-Admins haben keinen)
 *    - PrintAgent           (enrollt sich, bevor der Mandant feststeht)
 *
 *  Kind-Tabellen ohne eigene tenant_id (Isolation ueber den Parent):
 *    - InvoiceItem (via Invoice), GasStationBankAccount (via GasStation),
 *      Pivots wie gas_station_user / customer_gas_station / supplier_*.
 * ────────────────────────────────────────────────────────────────────────
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

        // Beim Erstellen automatisch die tenant_id setzen (T-1):
        // 1) aus dem zentralen TenantContext (API-Requests, Jobs, Commands),
        // 2) sonst wie bisher aus der Web-Session (Filament-Panels).
        // Explizit gesetzte tenant_id wird NIE ueberschrieben.
        static::creating(function ($model) {
            if (! empty($model->tenant_id)) {
                return;
            }

            $contextTenant = app(\App\Support\TenantContext::class)->get();
            if ($contextTenant) {
                $model->tenant_id = $contextTenant;
            } elseif (session()->has('tenant_id')) {
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
