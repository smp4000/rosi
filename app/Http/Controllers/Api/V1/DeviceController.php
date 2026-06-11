<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Device;
use App\Models\DeviceInvitation;
use App\Models\GasStation;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DeviceController extends ApiController
{
    /**
     * POST /api/v1/devices/register
     *
     * Geraet per QR-Code registrieren.
     * Der Admin generiert im Dashboard einen QR-Code → darin steckt ein setup_token.
     * Die App scannt den QR, schickt den Token hierher → bekommt einen device_token zurueck.
     *
     * Ablauf:
     * 1. App scannt QR-Code → bekommt setup_token
     * 2. App schickt: setup_token + Geraete-Infos
     * 3. API prueft Token, erstellt Device, gibt device_token zurueck
     * 4. App speichert device_token lokal → fertig
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'setup_token' => 'required|string|size:64',
            'device_name' => 'nullable|string|max:100',
            'device_os' => 'nullable|string|max:50',
            'app_version' => 'nullable|string|max:20',
        ]);

        // Einladung anhand des Tokens suchen
        $invitation = DeviceInvitation::where('token', $request->setup_token)
            ->where('status', 'pending')
            ->first();

        if (! $invitation) {
            return $this->error('Ungueltiger oder abgelaufener Setup-Code.', 404);
        }

        // Ist die Einladung noch gueltig (nicht abgelaufen)?
        if (! $invitation->isValid()) {
            return $this->error('Der Setup-Code ist abgelaufen. Bitte fordere einen neuen an.', 410);
        }

        // Neuen Device-Token generieren (das ist der "Ausweis" des Geraets)
        $plainToken = Str::random(64);

        // Geraet in der DB speichern
        $device = Device::create([
            'tenant_id' => $invitation->tenant_id,
            'station_id' => $invitation->station_id,
            'user_id' => $invitation->user_id,
            'device_type' => $invitation->user_id ? 'personal' : 'mde',
            'device_name' => $request->device_name,
            'device_os' => $request->device_os,
            'app_version' => $request->app_version,
            'device_token_hash' => Hash::make($plainToken),
            'is_active' => true,
            'last_seen_at' => now(),
        ]);

        // Einladung als "angenommen" markieren
        $invitation->markAccepted($device);

        // Token an die App zurueckgeben (nur dieses eine Mal sichtbar!)
        return $this->success([
            'device_id' => $device->id,
            'device_token' => $plainToken,
            'tenant_name' => $device->tenant->name ?? null,
            'station_name' => $device->station->name ?? null,
            'user_name' => $device->user->name ?? null,
        ], 'Geraet erfolgreich registriert.', 201);
    }

    /**
     * POST /api/v1/devices/register-station
     *
     * MDE-Geraet direkt an der Tankstelle anmelden.
     * An der Kasse haengt ein permanenter QR-Code (device_setup_token der Station).
     * Die App scannt ihn und schickt zusaetzlich ihre GPS-Position mit.
     *
     * GPS-Pruefung (Soft-Fail):
     * - Position passt (<= Radius)          → Geraet sofort aktiv
     * - Position fehlt oder weicht ab       → Geraet "wartet auf Freigabe" im Dashboard
     * - APP_ENV=local / POS_SKIP_GPS_CHECK  → Pruefung uebersprungen (Entwicklung)
     *
     * Stationswechsel: Schickt die App ihren vorhandenen device_token mit,
     * wird kein neues Geraet angelegt, sondern nur die Station umgehaengt.
     * Das geht nur vor Ort (GPS muss passen) — sonst Wechsel im Dashboard.
     */
    public function registerStation(Request $request): JsonResponse
    {
        $request->validate([
            'station_token' => 'required|string|max:32',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'device_token' => 'nullable|string',
            'device_name' => 'nullable|string|max:100',
            'device_os' => 'nullable|string|max:50',
            'app_version' => 'nullable|string|max:20',
        ]);

        // Station anhand des permanenten Setup-Tokens suchen
        // (ohne Tenant-Scope: die API hat keine Session, der Token identifiziert den Mandanten)
        $station = GasStation::withoutTenantScope()
            ->where('device_setup_token', strtoupper(trim($request->station_token)))
            ->first();

        if (! $station) {
            return $this->error('Ungueltiger Stations-Code. Bitte den QR-Code an der Kasse scannen.', 404);
        }

        // ── GPS-Pruefung ──────────────────────────────────────
        $skipGps = app()->environment('local') || config('pos.skip_gps_check');
        $radius = (int) config('pos.gps_radius_m', 250);

        $distance = null;
        if ($request->filled(['latitude', 'longitude'])) {
            $distance = $station->distanceToMeters(
                (float) $request->latitude,
                (float) $request->longitude
            );
        }

        // Bestanden, wenn: Entwicklung ODER Distanz berechenbar und im Radius
        $gpsOk = $skipGps || ($distance !== null && $distance <= $radius);

        // ── Stationswechsel: Geraet existiert schon ──────────
        if ($request->filled('device_token')) {
            $device = $this->findDeviceByToken($request->device_token);

            if ($device) {
                // Stationen anderer Mandanten sind tabu
                if ($device->tenant_id !== $station->tenant_id) {
                    return $this->error('Diese Station gehoert zu einem anderen Unternehmen.', 403);
                }

                if (! $gpsOk) {
                    return $this->error(
                        'Stationswechsel nur vor Ort moeglich (GPS-Position weicht ab'
                        . ($distance !== null ? ': ' . number_format($distance / 1000, 1, ',', '.') . ' km' : '')
                        . '). Alternativ kann die Station im Dashboard geaendert werden.',
                        422
                    );
                }

                $device->update([
                    'station_id' => $station->id,
                    // GPS hat gepasst → ein evtl. noch wartendes Geraet ist damit bestaetigt
                    'approval_status' => Device::APPROVAL_ACTIVE,
                    'registration_distance_m' => $distance,
                    'registration_latitude' => $request->latitude,
                    'registration_longitude' => $request->longitude,
                    'last_seen_at' => now(),
                ]);

                return $this->success([
                    'device_id' => $device->id,
                    'approval_status' => $device->approval_status,
                    'tenant_name' => $station->tenant->name ?? null,
                    'station_name' => $station->name,
                    'distance_m' => $distance,
                ], "Geraet ist jetzt an {$station->name} angemeldet.");
            }
            // Token unbekannt (Geraet wurde z.B. geloescht) → faellt durch zur Neu-Registrierung
        }

        // ── Neues Geraet registrieren ─────────────────────────
        $plainToken = Str::random(64);

        $device = Device::create([
            'tenant_id' => $station->tenant_id,
            'station_id' => $station->id,
            'user_id' => null,
            'device_type' => 'mde',
            'device_name' => $request->device_name,
            'device_os' => $request->device_os,
            'app_version' => $request->app_version,
            'device_token_hash' => Hash::make($plainToken),
            'is_active' => true,
            'approval_status' => $gpsOk ? Device::APPROVAL_ACTIVE : Device::APPROVAL_PENDING,
            'registration_distance_m' => $distance,
            'registration_latitude' => $request->latitude,
            'registration_longitude' => $request->longitude,
            'last_seen_at' => now(),
        ]);

        $message = $gpsOk
            ? 'Geraet erfolgreich registriert.'
            : 'Geraet registriert — wartet auf Freigabe im Dashboard (GPS-Position konnte nicht bestaetigt werden).';

        return $this->success([
            'device_id' => $device->id,
            'device_token' => $plainToken,
            'approval_status' => $device->approval_status,
            'tenant_name' => $station->tenant->name ?? null,
            'station_name' => $station->name,
            'distance_m' => $distance,
        ], $message, 201);
    }

    /**
     * Geraet anhand des Klartext-Tokens finden (Token ist gehasht gespeichert).
     */
    private function findDeviceByToken(string $plainToken): ?Device
    {
        if (empty($plainToken)) {
            return null;
        }

        $devices = Device::where('is_active', true)->get();
        foreach ($devices as $device) {
            if (Hash::check($plainToken, $device->device_token_hash)) {
                return $device;
            }
        }

        return null;
    }

    /**
     * POST /api/v1/devices/invite/accept
     *
     * Geraet per Einladungslink registrieren.
     * Gleiche Logik wie register, aber der Token kommt aus dem Link
     * statt aus einem QR-Code. Zusaetzlich setzt der MA seine PIN.
     *
     * Ablauf:
     * 1. MA klickt Link: https://rosi.app/pos-setup/abc123...
     * 2. App oeffnet sich mit dem Token
     * 3. MA legt seine PIN fest
     * 4. App schickt: token + PIN + Geraete-Infos
     * 5. API registriert Geraet + speichert PIN
     */
    public function acceptInvite(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string|size:64',
            'pin' => 'required|string|min:4|max:6',
            'device_name' => 'nullable|string|max:100',
            'device_os' => 'nullable|string|max:50',
            'app_version' => 'nullable|string|max:20',
        ]);

        // Einladung suchen
        $invitation = DeviceInvitation::where('token', $request->token)
            ->where('status', 'pending')
            ->first();

        if (! $invitation) {
            return $this->error('Ungueltiger oder abgelaufener Einladungslink.', 404);
        }

        if (! $invitation->isValid()) {
            return $this->error('Die Einladung ist abgelaufen. Bitte fordere eine neue an.', 410);
        }

        // PIN fuer den Mitarbeiter speichern (gehasht)
        $user = $invitation->user;
        $user->update(['pin_hash' => Hash::make($request->pin)]);

        // Geraet registrieren
        $plainToken = Str::random(64);

        $device = Device::create([
            'tenant_id' => $invitation->tenant_id,
            'station_id' => $invitation->station_id,
            'user_id' => $invitation->user_id,
            'device_type' => 'personal',
            'device_name' => $request->device_name,
            'device_os' => $request->device_os,
            'app_version' => $request->app_version,
            'device_token_hash' => Hash::make($plainToken),
            'is_active' => true,
            'last_seen_at' => now(),
        ]);

        $invitation->markAccepted($device);

        return $this->success([
            'device_id' => $device->id,
            'device_token' => $plainToken,
            'tenant_name' => $device->tenant->name ?? null,
            'station_name' => $device->station->name ?? null,
            'user_name' => $user->name,
        ], 'Willkommen! Geraet erfolgreich registriert.', 201);
    }

    /**
     * GET /api/v1/devices/verify?device_token=...
     *
     * Pruefen ob das Geraet noch registriert und aktiv ist.
     * Wird von der App beim Start aufgerufen, um zu erkennen ob
     * das Geraet im Dashboard geloescht oder deaktiviert wurde.
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'device_token' => 'required|string',
        ]);

        // Unter allen Geraeten suchen (auch deaktivierte, NICHT geloeschte)
        $devices = Device::whereNotNull('device_token_hash')->get();

        foreach ($devices as $device) {
            if (Hash::check($request->device_token, $device->device_token_hash)) {
                // Wartet noch auf Freigabe durch den Partner (GPS-Abweichung bei Anmeldung)
                // → kein Fehler, damit die App den Status anzeigen und weiter pollen kann
                if ($device->isPending()) {
                    return $this->success([
                        'device_id' => $device->id,
                        'station_name' => $device->station->name ?? 'Station',
                        'is_active' => false,
                        'approval_status' => $device->approval_status,
                    ], 'Geraet wartet auf Freigabe im Dashboard.');
                }

                if (! $device->isAllowed()) {
                    return $this->error('Geraet wurde deaktiviert.', 403);
                }

                // Geraet ist gueltig und aktiv
                $device->touch_last_seen();

                return $this->success([
                    'device_id' => $device->id,
                    'station_name' => $device->station->name ?? 'Station',
                    'is_active' => $device->is_active,
                    'approval_status' => $device->approval_status,
                ], 'Geraet ist registriert.');
            }
        }

        // Auch unter soft-deleted Geraeten suchen
        $trashedDevices = Device::onlyTrashed()
            ->whereNotNull('device_token_hash')
            ->where('device_token_hash', 'not like', 'DELETED_%')
            ->get();

        foreach ($trashedDevices as $device) {
            if (Hash::check($request->device_token, $device->device_token_hash)) {
                return $this->error('Geraet wurde entfernt. Bitte neu registrieren.', 404);
            }
        }

        // Token passt zu keinem Geraet
        return $this->error('Geraet nicht gefunden. Bitte neu registrieren.', 404);
    }

    /**
     * GET /api/v1/devices/invite/{token}/info
     *
     * Einladungs-Info abrufen.
     * Wenn der MA den Link oeffnet, zeigt die App erst mal:
     * "Hallo Max, du wirst an Tankstelle Aral Hauptstr. registriert."
     * Dafuer braucht die App die Infos BEVOR der MA sich registriert.
     */
    public function inviteInfo(string $token): JsonResponse
    {
        $invitation = DeviceInvitation::where('token', $token)
            ->where('status', 'pending')
            ->first();

        if (! $invitation) {
            return $this->error('Einladung nicht gefunden oder bereits verwendet.', 404);
        }

        if (! $invitation->isValid()) {
            return $this->error('Die Einladung ist abgelaufen.', 410);
        }

        return $this->success([
            'station_name' => $invitation->station->name ?? null,
            'user_name' => $invitation->user->name ?? null,
            'expires_at' => $invitation->expires_at->toIso8601String(),
        ]);
    }
}
