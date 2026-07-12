<?php

namespace App\Support;

/**
 * ──────────────────────────────────────────────────────────────────────────
 *  Zentraler Mandanten-Kontext (T-1 aus dem Sicherheits-Audit 07/2026)
 * ──────────────────────────────────────────────────────────────────────────
 *
 * PROBLEM vorher:
 *   Der TenantScope (automatischer WHERE-tenant_id-Filter auf allen
 *   mandantenfaehigen Models) las die Mandanten-ID AUSSCHLIESSLICH aus der
 *   Web-Session: `session('tenant_id')`. In API-Requests, Queue-Jobs und
 *   Artisan-Commands gibt es aber KEINE Web-Session → der Filter griff dort
 *   still und leise NICHT, und Queries sahen die Daten ALLER Mandanten.
 *   Solange nur explizit gefiltert wurde (where station_id/tenant_id), ging
 *   das gut — eine einzige vergessene Query waere aber eine stille
 *   Cross-Tenant-Datenpanne.
 *
 * LOESUNG:
 *   Diese Klasse ist die EINE Wahrheit fuer "welcher Mandant ist gerade
 *   aktiv". Sie ist als scoped-Singleton registriert (AppServiceProvider),
 *   lebt also genau einen Request/Job lang.
 *
 *   Gesetzt wird sie:
 *   - im Web:   weiterhin ueber die Session (EnsureTenantContext setzt
 *               session('tenant_id'); der TenantScope nutzt die Session als
 *               Fallback → Web-Verhalten ist 1:1 unveraendert)
 *   - in der API: durch die Middleware SetApiTenantContext — aus dem
 *               eingeloggten Sanctum-User ODER aus dem device_token
 *   - in Jobs/Commands: explizit per set()/runFor(), wo noetig.
 *
 *   Gelesen wird sie vom TenantScope (app/Scopes/TenantScope.php).
 *
 * WICHTIG fuer die Zukunft:
 *   - `set(null)` bzw. nie gesetzter Kontext bedeutet "kein Mandanten-Filter"
 *     (z.B. Super-Admin im Admin-Panel oder CLI-Poller, die bewusst ueber
 *     alle Mandanten laufen).
 *   - In neuen Queue-Jobs, die Mandanten-Daten anfassen, IMMER zuerst den
 *     Kontext setzen: `app(TenantContext::class)->set($tenantId);`
 */
class TenantContext
{
    /** Die aktuell aktive Mandanten-ID — null = kein Filter aktiv. */
    private ?string $tenantId = null;

    /** Mandanten-Kontext setzen (null = Filter ausschalten). */
    public function set(?string $tenantId): void
    {
        $this->tenantId = $tenantId ?: null;
    }

    /** Aktive Mandanten-ID lesen (null = kein Kontext gesetzt). */
    public function get(): ?string
    {
        return $this->tenantId;
    }

    /** Ist ein Mandanten-Kontext aktiv? */
    public function has(): bool
    {
        return $this->tenantId !== null;
    }

    /**
     * Einen Codeblock im Kontext eines bestimmten Mandanten ausfuehren und
     * danach den vorherigen Kontext wiederherstellen. Praktisch fuer
     * Commands/Jobs, die nacheinander mehrere Mandanten abarbeiten.
     */
    public function runFor(?string $tenantId, \Closure $callback): mixed
    {
        $previous = $this->tenantId;
        $this->set($tenantId);

        try {
            return $callback();
        } finally {
            $this->tenantId = $previous;
        }
    }
}
