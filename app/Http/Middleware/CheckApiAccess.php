<?php

namespace App\Http\Middleware;

use App\Models\Device;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

/**
 * API-Middleware: Prueft ob der Mandant Zugang hat (Trial aktiv oder Abo aktiv).
 *
 * Ermittelt den Tenant auf 3 Wegen (in dieser Reihenfolge):
 * 1. Authentifizierter User (auth:sanctum) → user->tenant
 * 2. device_token Parameter → Device → Station → Tenant
 * 3. station_id Parameter → Station → Tenant
 *
 * Gibt JSON 403 zurueck wenn Trial abgelaufen und kein Abo aktiv.
 */
class CheckApiAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolveTenant($request);

        if (! $tenant) {
            // Kein Tenant ermittelbar → durchlassen (andere Middleware kuemmert sich)
            return $next($request);
        }

        if (! $tenant->hasAccess()) {
            $message = 'Kein Zugang. ';

            if ($tenant->isTrialExpired()) {
                $message .= 'Die kostenlose Testphase ist abgelaufen. Bitte Abonnement abschliessen.';
            } elseif (! $tenant->is_active) {
                $message .= 'Der Zugang wurde deaktiviert. Bitte Support kontaktieren.';
            } else {
                $message .= 'Kein aktives Abonnement vorhanden.';
            }

            return response()->json([
                'success' => false,
                'message' => $message,
                'error_code' => 'subscription_required',
            ], 403);
        }

        return $next($request);
    }

    /**
     * Tenant aus dem Request ermitteln.
     */
    private function resolveTenant(Request $request): ?Tenant
    {
        // 1. Authentifizierter User (auth:sanctum)
        if ($request->user() && $request->user()->tenant) {
            return $request->user()->tenant;
        }

        // 2. Device-Token (die meisten POS-Routen)
        $deviceToken = $request->input('device_token')
            ?? $request->header('X-Device-Token');

        if ($deviceToken) {
            $device = $this->findDeviceByToken($deviceToken);
            if ($device && $device->station && $device->station->tenant) {
                return $device->station->tenant;
            }
        }

        return null;
    }

    /**
     * Geraet anhand des Device-Tokens finden (gleiche Logik wie AuthController).
     */
    private function findDeviceByToken(string $plainToken): ?Device
    {
        $devices = Device::where('is_active', true)
            ->whereNotNull('device_token_hash')
            ->get();

        foreach ($devices as $device) {
            if (Hash::check($plainToken, $device->device_token_hash)) {
                return $device;
            }
        }

        return null;
    }
}
