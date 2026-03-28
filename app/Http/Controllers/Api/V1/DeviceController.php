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
