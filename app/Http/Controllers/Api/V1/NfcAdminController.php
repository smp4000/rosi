<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Device;
use App\Models\GasStation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Admin-Funktionen der POS-App (NFC-Chips beschreiben).
 * Zugriff: Admin-Bereich in der App (3-Sekunden-Geste + Sicherheitscode),
 * authentifiziert ueber den device_token des Geraets.
 */
class NfcAdminController extends ApiController
{
    /**
     * GET /api/v1/admin/stations
     * Alle Tankstellen des Mandanten (fuer die Stations-Auswahl).
     */
    public function stations(Request $request): JsonResponse
    {
        $device = $this->findDevice($request->query('device_token', ''));
        if (! $device) {
            return $this->error('Geraet nicht erkannt.', 401);
        }

        $stations = GasStation::withoutTenantScope()
            ->where('tenant_id', $device->tenant_id)
            ->orderBy('name')
            ->get(['id', 'name', 'city'])
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'city' => $s->city,
            ])
            ->values();

        return $this->success(['stations' => $stations]);
    }

    /**
     * GET /api/v1/admin/station-employees?station_id=...
     * Mitarbeiter einer Station mit allen Daten fuer das NFC-Beschreiben:
     * fester Zugangscode (scan_code), ganzer Name, Adresse, Geburtsdatum.
     */
    public function stationEmployees(Request $request): JsonResponse
    {
        $request->validate([
            'device_token' => 'required|string',
            'station_id' => 'required|string',
        ]);

        $device = $this->findDevice($request->device_token);
        if (! $device) {
            return $this->error('Geraet nicht erkannt.', 401);
        }

        $station = GasStation::withoutTenantScope()
            ->where('tenant_id', $device->tenant_id)
            ->where('id', $request->station_id)
            ->first();

        if (! $station) {
            return $this->error('Station nicht gefunden.', 404);
        }

        $employees = $station->users()
            ->where('users.is_active', true)
            ->orderBy('users.last_name')
            ->orderBy('users.first_name')
            ->get()
            ->map(function ($user) {
                // Fester Zugangscode: einmal erzeugen, bleibt dann dauerhaft
                if (empty($user->scan_code)) {
                    $user->update(['scan_code' => strtoupper(Str::random(12))]);
                }

                $profile = $user->employeeProfile;

                // Geburtsdatum lesbar formatieren (verschluesselt gespeichert,
                // kommt je nach Cast als Carbon oder String)
                $dateOfBirth = null;
                if ($profile?->date_of_birth) {
                    try {
                        $dateOfBirth = \Carbon\Carbon::parse($profile->date_of_birth)->format('d.m.Y');
                    } catch (\Exception $e) {
                        $dateOfBirth = (string) $profile->date_of_birth;
                    }
                }

                return [
                    'id' => $user->id,
                    'name' => trim(($user->first_name ?? '') . ' ' . $user->last_name),
                    'full_name' => trim(($user->first_name ?? '') . ' ' . $user->last_name),
                    'scan_code' => $user->scan_code,
                    'address' => $profile ? trim($profile->full_address, " ,") : null,
                    'date_of_birth' => $dateOfBirth,
                ];
            })
            ->values();

        return $this->success([
            'station_name' => $station->name,
            'employees' => $employees,
        ]);
    }

    private function findDevice(string $plainToken): ?Device
    {
        return Device::findByPlainToken($plainToken);
    }
}
