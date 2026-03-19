<?php

namespace App\Filament\Partner\Resources\EmployeeResource\Pages;

use App\Filament\Partner\Resources\EmployeeResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

/**
 * Mitarbeiter direkt anlegen (ohne Einladung).
 * Erstellt User + EmployeeProfile in einer Transaktion.
 */
class CreateEmployee extends CreateRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenantId = session('tenant_id');

        // Tankstellen-Zuordnung entfernen (wird in afterCreate verarbeitet)
        unset($data['_gas_station_assignments']);

        // User-Felder setzen
        $data['tenant_id'] = $tenantId;
        $data['type'] = 'employee';
        $data['is_active'] = true;
        $data['email_verified_at'] = now();

        // Temporaeres Passwort generieren (Mitarbeiter muss es zuruecksetzen)
        if (empty($data['password'])) {
            $data['password'] = Hash::make(str()->random(16));
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $user = $this->record;
        $tenantId = session('tenant_id');

        // EmployeeProfile erstellen falls nicht via Relation schon angelegt
        if (! $user->employeeProfile) {
            $user->employeeProfile()->create([
                'tenant_id' => $tenantId,
                'status' => 'pending',
            ]);
        }

        // Standard-Rolle zuweisen
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        if (! $user->hasAnyRole(['partner', 'stationsleiter', 'mitarbeiter'])) {
            $user->assignRole('mitarbeiter');
        }

        // Tankstellen-Zuordnung aus Formulardaten
        $this->syncGasStationAssignments();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    private function syncGasStationAssignments(): void
    {
        $assignments = $this->data['_gas_station_assignments'] ?? [];
        $user = $this->record;

        $syncData = [];
        foreach ($assignments as $assignment) {
            $gsId = $assignment['gas_station_id'] ?? null;
            if (! $gsId) continue;

            $syncData[$gsId] = [
                'station_role' => $assignment['station_role'] ?? null,
                'is_primary' => $assignment['is_primary'] ?? false,
                'assigned_at' => now(),
            ];
        }

        if (! empty($syncData)) {
            $user->gasStations()->sync($syncData);

            // Falls Hauptstandort gesetzt: andere auf nicht-primaer
            foreach ($syncData as $gsId => $pivotData) {
                if ($pivotData['is_primary']) {
                    DB::table('gas_station_user')
                        ->where('user_id', $user->id)
                        ->where('gas_station_id', '!=', $gsId)
                        ->update(['is_primary' => false]);
                }
            }
        }
    }
}
