<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AuditLog;
use App\Models\Device;
use App\Models\GasStation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Admin-Funktionen der POS-App (NFC-Chips beschreiben).
 *
 * Zugriff: 3-Sekunden-Geste + Tages-Sicherheitscode (erste Huerde),
 * danach PERSOENLICHE Anmeldung mit PIN — nur Mitarbeiter mit der
 * Permission mde.admin.nfc-write an der Geraete-Station kommen rein.
 * Jeder Schreibvorgang wird im Audit-Log protokolliert (wer fuer wen).
 */
class NfcAdminController extends ApiController
{
    private const NFC_PERMISSION = 'mde.admin.nfc-write';
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

    /**
     * GET /api/v1/admin/authorized-users?device_token=...
     * Mitarbeiter der Geraete-Station, die den NFC-Admin nutzen duerfen
     * (Permission mde.admin.nfc-write) und eine PIN gesetzt haben.
     * Fuellt das Auswahl-Dropdown beim Admin-Login.
     */
    public function authorizedUsers(Request $request): JsonResponse
    {
        $device = $this->findDevice($request->query('device_token', ''));
        if (! $device) {
            return $this->error('Geraet nicht erkannt.', 401);
        }

        $users = $device->station->users()
            ->where('users.is_active', true)
            ->whereNotNull('pin_hash')
            ->orderBy('users.last_name')
            ->get()
            ->filter(fn (User $u) => $u->hasStationPermission(self::NFC_PERMISSION, $device->station_id))
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => trim(($u->first_name ?? '') . ' ' . $u->last_name),
            ])
            ->values();

        return $this->success(['users' => $users]);
    }

    /**
     * POST /api/v1/admin/authenticate
     * Persoenliche Anmeldung im Admin-Bereich: device_token + user_id + pin.
     * Prueft PIN UND die Permission mde.admin.nfc-write an der Geraete-Station.
     */
    public function authenticate(Request $request): JsonResponse
    {
        $request->validate([
            'device_token' => 'required|string',
            'user_id' => 'required|uuid',
            'pin' => 'required|string',
        ]);

        $device = $this->findDevice($request->device_token);
        if (! $device) {
            return $this->error('Geraet nicht erkannt.', 401);
        }

        $user = User::where('id', $request->user_id)
            ->where('tenant_id', $device->tenant_id)
            ->where('is_active', true)
            ->first();

        if (! $user || ! $user->pin_hash || ! Hash::check($request->pin, $user->pin_hash)) {
            return $this->error('Falsche PIN.', 401);
        }

        if (! $user->hasStationPermission(self::NFC_PERMISSION, $device->station_id)) {
            return $this->error('Keine Berechtigung fuer den Admin-Bereich.', 403);
        }

        return $this->success([
            'user_id' => $user->id,
            'name' => trim(($user->first_name ?? '') . ' ' . $user->last_name),
        ], 'Anmeldung erfolgreich.');
    }

    /**
     * POST /api/v1/admin/nfc-written
     * Protokolliert einen NFC-Schreibvorgang im Audit-Log:
     * device_token + admin_user_id (wer) + employee_id (fuer wen).
     */
    public function logNfcWritten(Request $request): JsonResponse
    {
        $request->validate([
            'device_token' => 'required|string',
            'admin_user_id' => 'required|uuid',
            'employee_id' => 'required|uuid',
        ]);

        $device = $this->findDevice($request->device_token);
        if (! $device) {
            return $this->error('Geraet nicht erkannt.', 401);
        }

        $admin = User::where('id', $request->admin_user_id)
            ->where('tenant_id', $device->tenant_id)
            ->first();

        // Nur protokollieren, wenn der Admin wirklich berechtigt ist
        if (! $admin || ! $admin->hasStationPermission(self::NFC_PERMISSION, $device->station_id)) {
            return $this->error('Nicht berechtigt.', 403);
        }

        $employee = User::where('id', $request->employee_id)
            ->where('tenant_id', $device->tenant_id)
            ->first();

        if (! $employee) {
            return $this->error('Mitarbeiter nicht gefunden.', 404);
        }

        AuditLog::create([
            'tenant_id' => $device->tenant_id,
            'user_id' => $admin->id,
            'user_type' => User::class,
            'action' => 'nfc_chip_written',
            'auditable_type' => User::class,
            'auditable_id' => $employee->id,
            'reason' => 'NFC-Chip beschrieben fuer '
                . trim(($employee->first_name ?? '') . ' ' . $employee->last_name)
                . ' an Station ' . ($device->station->name ?? '-')
                . ' durch ' . trim(($admin->first_name ?? '') . ' ' . $admin->last_name),
        ]);

        return $this->success(null, 'Protokolliert.');
    }

    private function findDevice(string $plainToken): ?Device
    {
        return Device::findByPlainToken($plainToken);
    }
}
