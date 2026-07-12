<?php

namespace App\Http\Middleware;

use App\Models\Device;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ──────────────────────────────────────────────────────────────────────────
 *  API-Middleware: Mandanten-Kontext fuer JEDEN API-Request setzen (T-1)
 * ──────────────────────────────────────────────────────────────────────────
 *
 * Laeuft auf der gesamten /api-Gruppe (registriert in bootstrap/app.php),
 * also VOR den Controllern. Ermittelt den Mandanten und traegt ihn in den
 * zentralen TenantContext ein — dadurch greift der automatische
 * tenant_id-Filter (TenantScope) auch in der API, wo es keine Web-Session
 * gibt. Vorher war das der blinde Fleck der Mandantentrennung.
 *
 * Ermittlungs-Reihenfolge:
 *   1. Eingeloggter Sanctum-User (Bearer-Token) → dessen tenant_id.
 *      `$request->user('sanctum')` funktioniert hier schon VOR der
 *      auth:sanctum-Route-Middleware, weil der Guard direkt aufloest.
 *   2. device_token (Body/Query oder Header X-Device-Token) → Mandant des
 *      Geraets. Nutzt die schnelle zentrale Suche aus A-4 (HMAC-Lookup),
 *      kostet also nur einen indizierten Query + 1 bcrypt-Check.
 *   3. Nichts von beidem (z.B. Geraete-Registrierung, App-Version-Check):
 *      Kontext bleibt leer → Scope filtert nicht → Verhalten wie bisher.
 *      Diese Endpunkte sichern sich selbst ab (Setup-Token, Freigaben).
 *
 * WICHTIG: Diese Middleware AUTHENTIFIZIERT nicht — sie setzt nur den
 * Datenfilter-Kontext. Authentifizierung machen weiterhin auth:sanctum
 * bzw. die device_token-Pruefungen in den Controllern.
 */
class SetApiTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $context = app(TenantContext::class);

        // 1) Sanctum-User (falls ein gueltiger Bearer-Token mitkommt)
        $user = $request->user('sanctum');
        if ($user && $user->tenant_id) {
            $context->set($user->tenant_id);

            return $next($request);
        }

        // 2) Geraete-Token (die meisten POS-Endpunkte)
        $deviceToken = $request->input('device_token')
            ?? $request->header('X-Device-Token');

        if ($deviceToken) {
            // findByPlainTokenForAuth: findet auch pending-Geraete — fuer den
            // DATENFILTER ist der Mandant auch dann korrekt; ob das Geraet
            // arbeiten darf, entscheidet weiterhin der jeweilige Endpunkt.
            $device = Device::findByPlainTokenForAuth($deviceToken);
            if ($device && $device->tenant_id) {
                $context->set($device->tenant_id);
            }
        }

        return $next($request);
    }
}
