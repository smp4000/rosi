<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Globaler Scope fuer Mandanten-Isolation.
 * Haengt automatisch `WHERE tenant_id = ?` an alle Queries der Models mit
 * dem BelongsToTenant-Trait.
 *
 * WOHER kommt die Mandanten-ID? (T-1, Sicherheits-Audit 07/2026)
 *   1. Vorrangig aus dem zentralen TenantContext (app/Support/TenantContext)
 *      — den setzt die API-Middleware (SetApiTenantContext) aus Sanctum-User
 *      oder device_token, und Jobs/Commands setzen ihn explizit. Damit greift
 *      die Isolation auch OHNE Web-Session (frueher der blinde Fleck: in
 *      API/CLI/Queue war session('tenant_id') immer null -> kein Filter!).
 *   2. Fallback: `session('tenant_id')` — das klassische Web-Verhalten der
 *      Filament-Panels (EnsureTenantContext setzt die Session beim Login).
 *      Dadurch bleibt das Dashboard-Verhalten 1:1 unveraendert.
 *
 * KEIN Kontext + KEINE Session (z.B. Artisan-Poller ueber alle Mandanten,
 * Super-Admin im Admin-Panel) = KEIN Filter — bewusst. Commands, die alle
 * Mandanten lesen, nutzen zusaetzlich withoutGlobalScopes(), damit die
 * Absicht im Code sichtbar bleibt.
 */
class TenantScope implements Scope
{
    /**
     * Scope auf den Builder anwenden.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // 1) Zentraler Kontext (API/Jobs/Commands) hat Vorrang ...
        $tenantId = app(\App\Support\TenantContext::class)->get();

        // 2) ... sonst klassisch die Web-Session (Filament-Panels).
        if (! $tenantId) {
            $tenantId = session('tenant_id');
        }

        if ($tenantId) {
            $builder->where($model->getTable() . '.tenant_id', $tenantId);
        }
    }
}
