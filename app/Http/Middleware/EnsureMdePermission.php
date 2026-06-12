<?php

namespace App\Http\Middleware;

use App\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Erzwingt eine MDE-Permission fuer API-Endpunkte der POS-App.
 *
 * Verwendung: ->middleware('mde.permission:mde.vouchers.issue')
 *
 * Die Station kommt aus dem device_token des Requests — so gilt die
 * Rolle des Mitarbeiters an GENAU dieser Tankstelle (Kassierer in Fulda,
 * Schichtleiter in Petersberg). Die App blendet Kacheln nur aus,
 * DIESE Pruefung ist die eigentliche Sicherheit.
 */
class EnsureMdePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Nicht angemeldet.',
            ], 401);
        }

        // Station aus dem Geraet ableiten (Body oder Query)
        $deviceToken = $request->input('device_token', $request->query('device_token', ''));
        $stationId = $deviceToken !== ''
            ? Device::findByPlainToken($deviceToken)?->station_id
            : null;

        if (! $user->hasStationPermission($permission, $stationId)) {
            return response()->json([
                'success' => false,
                'message' => 'Keine Berechtigung fuer diese Aktion.',
                'errors' => ['error_code' => 'permission_denied', 'permission' => $permission],
            ], 403);
        }

        return $next($request);
    }
}
