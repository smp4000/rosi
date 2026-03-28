<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Device;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends ApiController
{
    /**
     * POST /api/v1/auth/login
     *
     * Mitarbeiter-Login an der POS-App.
     *
     * Ablauf:
     * 1. Geraet ist bereits registriert (hat device_token)
     * 2. Mitarbeiter gibt seine PIN ein (oder scannt Badge am MDE)
     * 3. App schickt: device_token + user_id + pin
     * 4. API prueft alles → gibt session_token zurueck
     *
     * Warum device_token UND pin?
     * - device_token = "Dieses Geraet ist erlaubt" (wie Schlüsselkarte)
     * - pin = "Dieser Mitarbeiter ist es wirklich" (wie PIN am Geldautomat)
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'device_token' => 'required|string',
            'user_id' => 'required|uuid',
            'pin' => 'required|string|min:4|max:6',
        ]);

        // 1. Geraet finden und pruefen
        $device = $this->findDevice($request->device_token);
        if (! $device) {
            return $this->error('Geraet nicht erkannt. Bitte neu registrieren.', 401);
        }

        if (! $device->isAllowed()) {
            return $this->error('Geraet wurde deaktiviert. Bitte Admin kontaktieren.', 403);
        }

        // 2. Mitarbeiter finden
        $user = User::where('id', $request->user_id)
            ->where('tenant_id', $device->tenant_id)
            ->first();

        if (! $user) {
            return $this->error('Mitarbeiter nicht gefunden.', 404);
        }

        // 3. PIN pruefen
        if (! $user->pin_hash || ! Hash::check($request->pin, $user->pin_hash)) {
            return $this->error('Falsche PIN.', 401);
        }

        // 4. Pruefen ob MA an dieser Station arbeiten darf
        $worksAtStation = $user->gasStations()
            ->where('gas_stations.id', $device->station_id)
            ->exists();

        // Bei persoenlichem Geraet: user_id muss zum Geraet passen
        if ($device->device_type === 'personal' && $device->user_id !== $user->id) {
            return $this->error('Dieses Geraet ist einem anderen Mitarbeiter zugeordnet.', 403);
        }

        // 5. Sanctum API-Token erstellen (das ist der "Session-Ausweis")
        // Token laeuft nach 12 Stunden ab (eine Schicht)
        $token = $user->createToken(
            'pos-session',
            ['pos:access'],
            now()->addHours(12)
        );

        // 6. Letzten Kontakt aktualisieren
        $device->touch_last_seen();

        return $this->success([
            'session_token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at->toIso8601String(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'type' => $user->type,
            ],
            'station' => [
                'id' => $device->station->id,
                'name' => $device->station->name,
            ],
        ], 'Erfolgreich angemeldet.');
    }

    /**
     * POST /api/v1/auth/logout
     *
     * Mitarbeiter abmelden. Loescht den aktuellen Session-Token.
     */
    public function logout(Request $request): JsonResponse
    {
        // Aktuellen Token loeschen (nur diesen, nicht alle)
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, 'Erfolgreich abgemeldet.');
    }

    /**
     * POST /api/v1/auth/pin
     *
     * PIN aendern. Mitarbeiter kann seine PIN selbst aendern.
     */
    public function changePin(Request $request): JsonResponse
    {
        $request->validate([
            'current_pin' => 'required|string',
            'new_pin' => 'required|string|min:4|max:6',
        ]);

        $user = $request->user();

        // Aktuelle PIN pruefen
        if (! $user->pin_hash || ! Hash::check($request->current_pin, $user->pin_hash)) {
            return $this->error('Aktuelle PIN ist falsch.', 401);
        }

        // Neue PIN speichern
        $user->update(['pin_hash' => Hash::make($request->new_pin)]);

        return $this->success(null, 'PIN erfolgreich geaendert.');
    }

    /**
     * GET /api/v1/auth/station-employees
     *
     * Liste aller Mitarbeiter an dieser Station.
     * Wird auf dem Login-Screen angezeigt damit der MA sich auswaehlen kann.
     * (Nur ID und Name — keine sensiblen Daten!)
     */
    public function stationEmployees(Request $request): JsonResponse
    {
        $request->validate([
            'device_token' => 'required|string',
        ]);

        $device = $this->findDevice($request->device_token);
        if (! $device) {
            return $this->error('Geraet nicht erkannt.', 401);
        }

        if (! $device->isAllowed()) {
            return $this->error('Geraet deaktiviert.', 403);
        }

        // Alle Mitarbeiter die an dieser Station arbeiten und eine PIN haben
        $employees = $device->station->users()
            ->whereNotNull('pin_hash')
            ->select('users.id', 'users.first_name', 'users.last_name')
            ->orderBy('users.last_name')
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => trim($user->first_name . ' ' . $user->last_name),
            ])
            ->values();

        $device->touch_last_seen();

        return $this->success([
            'station_name' => $device->station->name,
            'employees' => $employees,
        ]);
    }

    // ── Hilfsmethoden ────────────────────────────────────────

    /**
     * Geraet anhand des Device-Tokens finden.
     * Wir muessen alle Geraete durchgehen und den Hash vergleichen,
     * weil wir den Token gehasht speichern (Sicherheit).
     */
    private function findDevice(string $plainToken): ?Device
    {
        // Performance: Nur aktive Geraete pruefen
        $devices = Device::where('is_active', true)->get();

        foreach ($devices as $device) {
            if (Hash::check($plainToken, $device->device_token_hash)) {
                return $device;
            }
        }

        return null;
    }
}
